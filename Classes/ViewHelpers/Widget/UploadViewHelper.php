<?php
namespace Ikiu\MediaUpload\ViewHelpers\Widget;

/*
 * This file is part of the Ikiu/MediaUpload project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;


/**
 * Widget which displays a media upload.
 */
class UploadViewHelper extends AbstractViewHelper
{

    protected $escapeOutput = false;

    public function __construct(
        protected ViewFactoryInterface $viewFactory,
    )
    { }

    /**
     * @return void
     * @throws \TYPO3\CMS\Fluid\Core\ViewHelper\Exception
     */
    public function initializeArguments()
    {
        $this->registerArgument('allowedExtensions', 'string', 'Allowed extension to be uploaded.', FALSE, '');
        $this->registerArgument('maximumSize', 'int', 'Maximum file size in Mo by default.', FALSE, 0);
        $this->registerArgument('sizeUnit', 'string', 'Whether it is Ko or Mo.', FALSE, 'Mo');
        $this->registerArgument('storage', 'int', 'The final storage identifier to which the file will be added eventually.', TRUE);
        $this->registerArgument('maximumItems', 'int', 'Maximum items to be uploaded', FALSE, 10);
        $this->registerArgument('property', 'int', 'The property name used for identifying and grouping uploaded files. Required if form contains multiple upload fields', FALSE, '');
    }

    /**
     * Compute the maximum size allowed to be uploaded.
     * Return a value in bytes.
     *
     * @return int
     */
    public function getMaximumSizeLabel()
    {

        $maximumSize = GeneralUtility::getMaxUploadFileSize() / 1024;
        if (!empty($this->widgetConfiguration['maximumSize'])) {
            $maximumSize = $this->widgetConfiguration['maximumSize'];
        }

        return $maximumSize;
    }

    /**
     * Compute the maximum size allowed to be uploaded.
     * Return a value in bytes.
     *
     * @return int
     */
    public function getMaximumSize()
    {

        $maximumSize = GeneralUtility::getMaxUploadFileSize() * 1024;
        if (!empty($this->widgetConfiguration['maximumSize'])) {
            $maximumSize = $this->widgetConfiguration['maximumSize'];

            if ($this->widgetConfiguration['sizeUnit'] === 'Ko') {
                $maximumSize = $maximumSize * 1024;
            } else {
                $maximumSize = $maximumSize * pow(1024, 2);
            }
        }

        return $maximumSize;
    }

    /**
     * Compute the allowed extensions to be uploaded.
     *
     * @return string
     */
    public function getAllowedExtensions()
    {
        $allowedExtensions = '';

        if (!empty($this->widgetConfiguration['allowedExtensions'])) {
            $allowedExtensions = GeneralUtility::trimExplode(',', $this->widgetConfiguration['allowedExtensions'], TRUE);
        } elseif ($this->widgetConfiguration['storage'] > 0 && ExtensionManagementUtility::isLoaded('media')) {
            $allowedExtensions = PermissionUtility::getInstance()->getAllowedExtensions($this->widgetConfiguration['storage']);
        }

        // Format to be eventually consumed by JavaScript.
        if (!empty($allowedExtensions)) {
            $allowedExtensions = implode("','", $allowedExtensions);
        }

        return $allowedExtensions;
    }

    /**
     * Returns an carousel widget
     *
     * @return string
     */
    public function render()
    {
        # generate a standalone view and render the template ViewHelpers/Widget/Upload/Index.html
        $viewFactoryData = new ViewFactoryData(
            templateRootPaths: ['EXT:media_upload/Resources/Private/Templates'],
            partialRootPaths: ['EXT:media_upload/Resources/Private/Partials'],
            layoutRootPaths: ['EXT:media_upload/Resources/Private/Layouts'],
        );

        $view = $this->viewFactory->create($viewFactoryData);

        $allowedExtensions = $this->arguments['allowedExtensions'];
        # check if str contains comma
        if (!empty($allowedExtensions) && str_contains($allowedExtensions, ',')) {
            # replace all , with ','
            $allowedExtensions = str_replace(',', "','", $allowedExtensions);
        }

        $view->assignMultiple([
            'uniqueId' => uniqid(),
            'allowedExtensions' => $allowedExtensions,
            'maximumSizeLabel' => $this->arguments['maximumSize'],
            'sizeUnit' => $this->arguments['sizeUnit'],
            'storage' => $this->arguments['storage'],
            'maximumItems' => $this->arguments['maximumItems'],
            'property' => $this->arguments['property'],
        ]);

        return $view->render("Upload.html");
    }
}
