<?php
namespace Fab\MediaUpload\FileUpload\Optimizer;

/*
 * This file is part of the Fab/MediaUpload project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use Fab\Media\Module\MediaModule;
use Fab\MediaUpload\Dimension;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Fab\MediaUpload\FileUpload\ImageOptimizerInterface;
use \Fab\MediaUpload\FileUpload\UploadedFileInterface;
use \TYPO3\CMS\Core\Resource\ResourceStorage;

/**
 * Class that optimize an image according to some settings.
 */
class Resize implements ImageOptimizerInterface
{

    /**
     * @var \TYPO3\CMS\Frontend\Imaging\GifBuilder
     */
    protected mixed $gifCreator;

    /**
     * @var ResourceStorage
     */
    protected ?ResourceStorage $storage;

    /**
     * @param ResourceStorage|null $storage
     */
    public function __construct(ResourceStorage $storage = NULL)
    {
        $this->storage = $storage;
        $this->gifCreator = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Imaging\\GifBuilder');
        // $this->gifCreator->init();
        $this->gifCreator->absPrefix = \TYPO3\CMS\Core\Core\Environment::getPublicPath() . '/' ;
    }

    /**
     * Optimize the given uploaded image.
     *
     * @param UploadedFileInterface $uploadedFile
     * @return \Fab\MediaUpload\FileUpload\UploadedFileInterface
     * @throws \Exception
     * @throws \InvalidArgumentException
     */
    public function optimize(UploadedFileInterface $uploadedFile): UploadedFileInterface
    {
        $imageInfo = getimagesize($uploadedFile->getFileWithAbsolutePath());

        list($currentWidth, $currentHeight) = $imageInfo;

        // resize an image if this one is bigger than telling by the settings.
        if (is_object($this->storage)) {
            $storageRecord = $this->storage->getStorageRecord();
        }

        if (isset($storageRecord) && strlen($storageRecord['maximum_dimension_original_image'] ?? "") > 0) {

            /** @var Dimension $imageDimension */
            $imageDimension = GeneralUtility::makeInstance(Dimension::class, $storageRecord['maximum_dimension_original_image']);
            if ($currentWidth > $imageDimension->getWidth() || $currentHeight > $imageDimension->getHeight()) {

                // resize taking the width as reference
                $this->resize($uploadedFile->getFileWithAbsolutePath(), $imageDimension->getWidth(), $imageDimension->getHeight());
            }
        }
        return $uploadedFile;
    }

    /**
     * Resize an image according to given parameter.
     *
     * @throws \Exception
     * @param string $fileNameAndPath
     * @param int $width
     * @param int $height
     * @return void
     */
    public function resize($fileNameAndPath, $width = 0, $height = 0)
    {

        // Skip profile of the image
        $imParams = '###SkipStripProfile###';
        $options = array(
            'maxW' => $width,
            'maxH' => $height,
        );

        $tempFileInfo = $this->gifCreator->imageMagickConvert($fileNameAndPath, '', '', '', $imParams, '', $options, TRUE);
        if ($tempFileInfo) {

            // Overwrite original file
            @unlink($fileNameAndPath);
            @rename($tempFileInfo[3], $fileNameAndPath);
        }
    }

    /**
     * Escapes a file name so it can safely be used on the command line.
     *
     * @see \TYPO3\CMS\Core\Imaging\GraphicalFunctions
     * @param string $inputName filename to safeguard, must not be empty
     * @return string $inputName escaped as needed
     */
    protected function wrapFileName($inputName)
    {
        $currentLocale = '';
        if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['UTF8filesystem']) {
            $currentLocale = setlocale(LC_CTYPE, 0);
            setlocale(LC_CTYPE, $GLOBALS['TYPO3_CONF_VARS']['SYS']['systemLocale']);
        }
        $escapedInputName = escapeshellarg($inputName);
        if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['UTF8filesystem']) {
            setlocale(LC_CTYPE, $currentLocale);
        }
        return $escapedInputName;
    }


}
