<?php
namespace Ikiu\MediaUpload\ViewHelpers\Widget;

/*
 * This file is part of the Ikiu/MediaUpload project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use Nng\Nnhelpers\ViewHelpers\AbstractViewHelper;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\View\ViewFactoryData;


/**
 * Widget which displays a media upload.
 */
class ShowUploadedViewHelper extends AbstractViewHelper
{

    public function __construct(
        protected \Ikiu\MediaUpload\Service\UploadFileService $uploadFileService,
        protected \TYPO3\CMS\Core\View\ViewFactoryInterface $viewFactory,
    )
    { }

    /**
     * @return void
     */
    public function initializeArguments()
    {
        $this->registerArgument('property', 'int', 'The property name used for identifying and grouping uploaded files. Required if form contains multiple upload fields', FALSE, '');
    }

    private function getRequest(): ServerRequestInterface|null
    {
        if ($this->renderingContext->hasAttribute(ServerRequestInterface::class)) {
            return $this->renderingContext->getAttribute(ServerRequestInterface::class);
        }
        return null;
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


        $property = $this->arguments['property'] ?? '';
        $request = $this->getRequest();
        debug($request->getParsedBody()["tx_mediaupload_pi1"]);
        $view->assign('property', $property);
        $view->assign('uploadedFileList', $this->uploadFileService->getUploadedFileList($request, $property));
        $view->assign('uploadedFiles', $this->uploadFileService->getUploadedFiles($request, $property));

        return $view->render("ShowUploaded.html");
    }
}
