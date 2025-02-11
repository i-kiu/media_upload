<?php
namespace Ikiu\MediaUpload\FileUpload;

/*
 * This file is part of the Ikiu/MediaUpload project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Http\UploadedFile;

/**
 * Class that optimize an image according to some settings.
 */
class ImageOptimizer implements SingletonInterface
{

    /**
     * @var array
     */
    protected $optimizers = array();

    /**
     * @var \TYPO3\CMS\Core\Resource\ResourceStorage
     */
    protected $storage;

    /**
     * Returns a class instance.
     *
     * @return \Ikiu\MediaUpload\FileUpload\ImageOptimizer
     * @param \TYPO3\CMS\Core\Resource\ResourceStorage $storage
     */
    static public function getInstance($storage = NULL)
    {
        return GeneralUtility::makeInstance('Ikiu\MediaUpload\FileUpload\ImageOptimizer', $storage);
    }

    /**
     * Constructor
     *
     * @param \TYPO3\CMS\Core\Resource\ResourceStorage $storage
     *@return \Ikiu\MediaUpload\FileUpload\ImageOptimizer
     */
    public function __construct(\TYPO3\CMS\Core\Resource\ResourceStorage $storage = NULL)
    {
        $this->storage = $storage;
        $this->add('Ikiu\MediaUpload\FileUpload\Optimizer\Resize');
        $this->add('Ikiu\MediaUpload\FileUpload\Optimizer\Rotate');
    }

    /**
     * Register a new optimizer
     *
     * @param string $className
     * @return void
     */
    public function add($className)
    {
        $this->optimizers[] = $className;
    }

    /**
     * Un-register a new optimizer
     *
     * @param string $className
     * @return void
     */
    public function remove($className)
    {
        if (in_array($className, $this->optimizers)) {
            $key = array_search($className, $this->optimizers);
            unset($this->optimizers[$key]);
        }
    }

    /**
     * Optimize an image
     *
     * @param UploadedFileInterface $uploadedFile
     * @return UploadedFileInterface
     */
    public function optimize(UploadedFileInterface $uploadedFile): UploadedFileInterface
    {

        foreach ($this->optimizers as $optimizer) {

            /** @var $optimizer \Ikiu\MediaUpload\FileUpload\ImageOptimizerInterface */
            $optimizer = GeneralUtility::makeInstance($optimizer, $this->storage);
            $uploadedFile = $optimizer->optimize($uploadedFile);
        }

        return $uploadedFile;
    }
}
