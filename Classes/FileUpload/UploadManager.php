<?php
namespace Fab\MediaUpload\FileUpload;

/*
 * This file is part of the Fab/MediaUpload project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use BadFunctionCallException;
use Fab\Media\Utility\PermissionUtility;
use Fab\MediaUpload\Utility\UploadUtility;
use Fab\MediaUpload\Utility\UuidUtility;
use FilesystemIterator;
use InvalidArgumentException;
use Normalizer;
use Psr\Http\Message\ServerRequestInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use TYPO3\CMS\Core\Resource\AbstractFile;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileType;
use \TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\Security\FileNameValidator;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\File\BasicFileUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * Class that encapsulates the file-upload internals
 *
 * @see original implementation: https://github.com/valums/file-uploader/blob/master/server/php.php
 */
class UploadManager
{

    const UPLOAD_FOLDER = 'typo3temp/MediaUpload';

    /**
     * @var string|int|NULL
     */
    protected string|int|null $sizeLimit;

    /**
     * @var string
     */
    protected ?string $uploadFolder = null;

    /**
     * @var ResourceStorage|null
     */
    protected ?ResourceStorage $storage;

    /**
     * Name of the file input in the DOM.
     *
     * @var string
     */
    protected string $inputName = 'qqfile';

    /**
     * @param ResourceStorage|null $storage
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @\TYPO3\CMS\Extbase\Annotation\Inject
     */
    public function __construct(ResourceStorage $storage = null)
    {
        // max file size in bytes
        $this->sizeLimit = GeneralUtility::getMaxUploadFileSize() * 1024;
        $this->storage = $storage;

        $this->checkServerSettings();
    }

    /**
     * Handle the uploaded file.
     *
     * @return UploadedFileInterface
     * @throws BadFunctionCallException
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function handleUpload(ServerRequestInterface $request): UploadedFileInterface
    {

        /** @var $uploadedFile UploadedFileInterface */
        if (UploadUtility::getInstance()->isMultiparted()) {

            // Default case
            $uploadedFile = GeneralUtility::makeInstance(MultipartedFile::class);
        } elseif (UploadUtility::getInstance()->isOctetStreamed()) {

            // Fine Upload plugin would use it if forceEncoded = false and paramsInBody = false
            $uploadedFile = GeneralUtility::makeInstance(StreamedFile::class);
        } elseif (UploadUtility::getInstance()->isUrlEncoded()) {

            // Used for image resizing in BE
            $uploadedFile = GeneralUtility::makeInstance(Base64File::class);
        } else {
            $this->throwException('Could not instantiate an upload object... No file was uploaded?');
        }

        $qquuid = $request->getParsedBody()['qquuid'] ?? $request->getQueryParams()['qquuid'] ?? null;

        $this->initializeUploadFolder($qquuid);

        $this->checkFileSize($uploadedFile->getSize());

        $fileName = $this->getFileName($uploadedFile);
        $this->checkFileAllowed($fileName);

        $saved = $uploadedFile->setInputName($this->getInputName())
            // @extensionScannerIgnoreLine
            ->setUploadFolder($this->getUploadFolder($qquuid))
            ->setName($fileName)
            ->save();

        if (!$saved) {
            $this->throwException('Could not save uploaded file. The upload was cancelled, or server error encountered');
        }

        // Optimize file if the uploaded file is an image.
        if ($uploadedFile->getType() === FileType::IMAGE) {
            $uploadedFile = ImageOptimizer::getInstance($this->storage)->optimize($uploadedFile);
        }

        // Clean up empty sub-folder
        $this->cleanUpEmptySubFolder();

        return $uploadedFile;
    }

    /**
     * Remove possible empty sub directory as a sanity measure.
     */
    protected function cleanUpEmptySubFolder(): void
    {
        // Get recursive iterator against the upload folder.
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                self::UPLOAD_FOLDER,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        // If the folder is found empty -> remove it as file was already processed.
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                $isDirEmpty = !(new \FilesystemIterator($file))->valid();

                if ($isDirEmpty) {
                    rmdir((string)$file);
                }
            }
        }
    }

    /**
     * Internal function that checks if server's may sizes match the
     * object's maximum size for uploads.
     *
     * @return void
     * @throws RuntimeException
     */
    protected function checkServerSettings(): void
    {
        $postSize = $this->toBytes(ini_get('post_max_size'));

        $uploadSize = $this->toBytes(ini_get('upload_max_filesize'));

        if ($postSize < $this->sizeLimit || $uploadSize < $this->sizeLimit) {
            $size = max(1, $this->sizeLimit / 1024 / 1024) . 'M';
            $this->throwException('increase post_max_size and upload_max_filesize to ' . $size);
        }
    }

    /**
     * Convert a given size with units to bytes.
     *
     * @param string $str
     * @return int|string
     */
    protected function toBytes(string $str): int|string
    {
        $val = substr(trim($str), 0, -1);
        $last = strtolower($str[strlen($str) - 1]);
        switch ($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        return $val;
    }

    /**
     * Return a file name given an uploaded file
     *
     * @param UploadedFileInterface $uploadedFile
     * @return string
     */
    protected function getFileName(UploadedFileInterface $uploadedFile): string
    {
        $pathInfo = pathinfo($uploadedFile->getOriginalName());
        $fileName = $this->sanitizeFileName($pathInfo['filename']);
        $fileNameWithExtension = $fileName;
        if (!empty($pathInfo['extension'])) {
            $fileNameWithExtension = sprintf('%s.%s', $fileName, $pathInfo['extension']);
        }
        return $fileNameWithExtension;
    }

    /**
     * Check whether the file size does not exceed the allowed limit
     *
     * @param int $size
     * @throws RuntimeException
     */
    protected function checkFileSize(int $size): void
    {
        if ($size === 0) {
            $this->throwException('File is empty');
        }

        if ($size > $this->sizeLimit) {
            $this->throwException('File is too large');
        }
    }

    /**
     * Check whether the file is allowed
     *
     * @param string $fileName
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws BadFunctionCallException
     */
    protected function checkFileAllowed(string $fileName): void
    {
        $isAllowed = $this->checkFileExtensionPermission($fileName);
        if (!$isAllowed) {
            $this->throwException('File has an invalid extension as defined by the system.');
        }

        if (ExtensionManagementUtility::isLoaded('media')) {

            $fileExtension = $this->getFileExtension($fileName);
            $allowedFileExtensions = PermissionUtility::getInstance()->getAllowedExtensions($this->storage);
            if (!in_array($fileExtension, $allowedFileExtensions, true)) {
                $this->throwException('File has an invalid extension as defined in the storage settings');
            }
        }
    }

    /**
     * @param string $fileName
     * @return string
     */
    protected function getFileExtension(string $fileName): string
    {
        $fileInfo = GeneralUtility::split_fileref($fileName);
        return strtolower($fileInfo['fileext']);
    }

    /**
     * If the fileName is given, check it against the
     *
     * @param string $fileName Full filename
     * @return boolean true if extension/filename is allowed
     *@see typo3/sysext/core/Classes/Resource/Security/FileNameValidator.php
     */
    protected function checkFileExtensionPermission(string $fileName): bool
    {
        // this nextline does the same as before the whole function
        $isAllowed =  GeneralUtility::makeInstance(FileNameValidator::class)->isValid((string)$fileName);
        if ($isAllowed) {

            // not really needed
            // If someone wants to Set up the permissions for the file extension in Extension configuration

            $fileExtensionPermissions = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class) ->get('media_upload');

            $allow = StringUtility::uniqueList(strtolower($fileExtensionPermissions['fileExtensionsAllow']));
            $deny = StringUtility::uniqueList(strtolower($fileExtensionPermissions['fileExtensionsDeny']));
            $fileExtension = $this->getFileExtension($fileName);
            if ($fileExtension !== '') {
                // If the extension is found amongst the allowed types, we return true immediately
                if ( $allow === '*' || $allow == '' || GeneralUtility::inList( $allow , $fileExtension)) {
                    return true;
                }
                // If the extension is found amongst the denied types, we return false immediately
                if ( $deny === '*' || GeneralUtility::inList($deny, $fileExtension)) {
                    return false;
                }
                // If no match we return true
                return true;
            } else {
                if ( $allow === '*') {
                    return true;
                }
                if ($deny === '*') {
                    return false;
                }
                return true;
            }
        }
        return false;
    }

    /**
     * Sanitize the file name for the web.
     * It has been noticed issues when letting done this work by FAL. Give it a little hand.
     *
     * @see https://github.com/alixaxel/phunction/blob/master/phunction/Text.php#L252
     * @param string $fileName
     * @param string $slug
     * @param string|null $extra
     * @return string
     */
    protected function sanitizeFileName(string $fileName, string $slug = '-', string $extra = NULL): string
    {
        return trim(preg_replace('~[^0-9a-z_' . preg_quote($extra, '~') . ']+~i', $slug, $this->unAccent($fileName)), $slug);
    }

    /**
     * Remove accent from a string
     *
     * @see https://github.com/alixaxel/phunction/blob/master/phunction/Text.php#L297
     * @param string $string
     * @return string
     */
    protected function unAccent(string $string): string
    {
        $searches = array('ç', 'æ', 'œ', 'á', 'é', 'í', 'ó', 'ú', 'à', 'è', 'ì', 'ò', 'ù', 'ä', 'ë', 'ï', 'ö', 'ü', 'ÿ', 'â', 'ê', 'î', 'ô', 'û', 'å', 'e', 'i', 'ø', 'u');
        $replaces = array('c', 'ae', 'oe', 'a', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u', 'y', 'a', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u');
        $sanitizedString = str_replace($searches, $replaces, $string);

        if (extension_loaded('intl') === true) {
            $sanitizedString = Normalizer::normalize($sanitizedString, Normalizer::FORM_KD);
        }
        return $sanitizedString;
    }

    /**
     * @param string $message
     * @throws RuntimeException
     */
    protected function throwException(string $message)
    {
        throw new RuntimeException($message, 1357510420);
    }

    /**
     * Initialize Upload Folder.
     *
     * @return void
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    protected function initializeUploadFolder(string $qquuid = null): void
    {
        $uploadFolder = $this->getUploadFolder($qquuid);
        // Initialize the upload folder for file transfer and create it if not yet existing
        if (!file_exists($uploadFolder)) {
            GeneralUtility::mkdir_deep($uploadFolder);
        }

        // Check whether the upload folder is writable
        if (!is_writable($uploadFolder)) {
            $this->throwException('Server error. Upload directory is not writable.');
        }
    }

    /**
     * @return int|NULL|string
     */
    public function getSizeLimit(): float|int|string|null
    {
        return $this->sizeLimit;
    }

    /**
     * @param int|string|NULL $sizeLimit
     * @return $this
     */
    public function setSizeLimit(int|string|null $sizeLimit): static
    {
        $this->sizeLimit = $sizeLimit;
        return $this;
    }

    /**
     * @param string|null $folderIdentifier
     * @return string
     * @throws InvalidArgumentException
     */
    public function getUploadFolder(string $folderIdentifier = null): string
    {
        if ($this->uploadFolder === null) {
            $this->uploadFolder = \TYPO3\CMS\Core\Core\Environment::getPublicPath() . '/' . self::UPLOAD_FOLDER;

            if (UuidUtility::getInstance()->isValid($folderIdentifier)) {
                $this->uploadFolder = $this->uploadFolder . DIRECTORY_SEPARATOR . $folderIdentifier;
            }

        }
        return $this->uploadFolder;
    }

    /**
     * @param string $uploadFolder
     * @return $this
     */
    public function setUploadFolder(string $uploadFolder): static
    {
        $this->uploadFolder = $uploadFolder;
        return $this;
    }

    /**
     * @return string
     */
    public function getInputName(): string
    {
        return $this->inputName;
    }

    /**
     * @param string $inputName
     * @return $this
     */
    public function setInputName(string $inputName): static
    {
        $this->inputName = $inputName;
        return $this;
    }

    /**
     * @return ResourceStorage|null
     */
    public function getStorage(): ?ResourceStorage
    {
        return $this->storage;
    }

    /**
     * @param ResourceStorage $storage
     * @return $this
     */
    public function setStorage(ResourceStorage $storage): static
    {
        $this->storage = $storage;
        return $this;
    }

}
