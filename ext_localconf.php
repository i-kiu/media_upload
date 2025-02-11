<?php



//if (!defined('TYPO3_MODE')) die ('Access denied!');

call_user_func(function () {

    $configuration = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class) ->get('media_upload');

    if (false === isset($configuration['autoload_typoscript']) || true === (bool)$configuration['autoload_typoscript']) {
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript(
            'media_upload',
            'constants',
            '<INCLUDE_TYPOSCRIPT: source="FILE:EXT:media_upload/Configuration/TypoScript/constants.typoscript">'
        );
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript(
            'media_upload',
            'setup',
            '<INCLUDE_TYPOSCRIPT: source="FILE:EXT:media_upload/Configuration/TypoScript/setup.typoscript">'
        );
    }

    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
        'media_upload',
        'Upload',
        array(
            \Ikiu\MediaUpload\Controller\MediaUploadController::class => 'upload',
        ),
        // non-cacheable actions
        array(
            \Ikiu\MediaUpload\Controller\MediaUploadController::class => 'upload',
        )
    );

    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
        'media_upload',
        'Delete',
        array(
            \Ikiu\MediaUpload\Controller\MediaUploadController::class => 'delete',
        ),
        // non-cacheable actions
        array(
            \Ikiu\MediaUpload\Controller\MediaUploadController::class => 'delete',
        )
    );
    // command line is replaced by symphony command:
    // ./vendor/bin/typo3cms mediaupload:removeTempFiles rundry=1

});
