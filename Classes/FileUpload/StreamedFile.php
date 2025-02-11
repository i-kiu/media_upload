<?php
namespace Ikiu\MediaUpload\FileUpload;

/*
 * This file is part of the Ikiu/MediaUpload project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */


/**
 * Handle file uploads via XMLHttpRequest.
 *
 * @see original implementation: https://github.com/valums/file-uploader/blob/master/server/php.php
 */
class StreamedFile extends \Ikiu\MediaUpload\FileUpload\UploadedFileAbstract
{

    /**
     * @var string
     */
    protected $inputName = 'qqfile';

    /**
     * @var string
     */
    protected $uploadFolder;

    /**
     * @var string
     */
    protected $name;

    /**
     * Save the file to the specified path
     *
     * @return boolean TRUE on success
     * @throws \RuntimeException
     */
    public function save(): bool
    {

        if (is_null($this->uploadFolder)) {
            throw new \RuntimeException('Upload folder is not defined', 1361787579);
        }

        if (is_null($this->name)) {
            throw new \RuntimeException('File name is not defined', 1361787580);
        }

        $input = fopen("php://input", "r");
        $temp = tmpfile();
        $realSize = stream_copy_to_stream($input, $temp);
        fclose($input);

        if ($realSize != $this->getSize()) {
            return FALSE;
        }

        $target = fopen($this->getFileWithAbsolutePath(), "w");
        fseek($temp, 0, SEEK_SET);
        stream_copy_to_stream($temp, $target);
        fclose($target);

        return TRUE;
    }

    /**
     * Get the original file name.
     *
     * @return string
     */
    public function getOriginalName(): string
    {
        return $_GET[$this->inputName];
    }

    /**
     * Get the file size
     *
     * @throws \Exception
     * @return integer file-size in byte
     */
    public function getSize(): int
    {
        if (isset($GLOBALS['_SERVER']['CONTENT_LENGTH'])) {
            return (int)$GLOBALS['_SERVER']['CONTENT_LENGTH'];
        } else {
            throw new \Exception('Getting content length is not supported.');
        }
    }

    /**
     * Get MIME type of file.
     *
     * @return string|boolean MIME type. eg, text/html, FALSE on error
     */
    public function getMimeType(): bool|string
    {
        $this->checkFileExistence();
        if (function_exists('finfo_file')) {
            $fileInfo = new \finfo();
            return $fileInfo->file($this->getFileWithAbsolutePath(), FILEINFO_MIME_TYPE);
        } elseif (function_exists('mime_content_type')) {
            return mime_content_type($this->getFileWithAbsolutePath());
        }
        return FALSE;
    }
}
