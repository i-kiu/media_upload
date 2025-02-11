<?php
namespace Fab\MediaUpload\Utility;

/*
 * This file is part of the Fab/MediaUpload project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class UploadUtility
 */
class UploadUtility implements SingletonInterface
{

    /**
     * Returns a class instance.
     *
     * @return UploadUtility
     * @throws \InvalidArgumentException
     */
    static public function getInstance(): UploadUtility
    {
        return GeneralUtility::makeInstance(self::class);
    }

    /**
     * Tells whether the content type is valid.
     *
     * @return bool
     */
    public function hasValidContentType(): bool
    {
        return isset($GLOBALS['_SERVER']['CONTENT_TYPE']);
    }

    /**
     * Tells whether the form is multiparted, e.g "multipart/form-data"
     *
     * @return bool
     */
    public function isMultiparted(): bool
    {
        return str_starts_with(strtolower($GLOBALS['_SERVER']['CONTENT_TYPE']), 'multipart/form-data');
    }

    /**
     * Tells whether the form is URL encoded, e.g "application/x-www-form-urlencoded; charset=UTF-8"
     *
     * @return bool
     */
    public function isUrlEncoded(): bool
    {
        return str_starts_with(strtolower($GLOBALS['_SERVER']['CONTENT_TYPE']), 'application/x-www-form-urlencoded');
    }

    /**
     * Tells whether the form is octet streamed, e.g "application/x-www-form-urlencoded; charset=UTF-8"
     *
     * @return bool
     */
    public function isOctetStreamed(): bool
    {
        return str_starts_with(strtolower($GLOBALS['_SERVER']['CONTENT_TYPE']), 'application/octet-stream');
    }

}
