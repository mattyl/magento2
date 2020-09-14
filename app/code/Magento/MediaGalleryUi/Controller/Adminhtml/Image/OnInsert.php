<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\MediaGalleryUi\Controller\Adminhtml\Image;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\MediaGalleryUiApi\Api\GetInsertImageDataInterface;

/**
 * OnInsert action returns on insert image details
 */
class OnInsert extends Action implements HttpPostActionInterface
{
    /**
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Magento_MediaGalleryUiApi::insert_assets';

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var GetInsertImageDataInterface
     */
    private $getInsertImageData;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param GetInsertImageDataInterface|null $getInsertImageData
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        GetInsertImageDataInterface $getInsertImageData
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->getInsertImageData = $getInsertImageData;
    }

    /**
     * Return a content (just a link or an html block) for inserting image to the content
     *
     * @return ResultInterface
     */
    public function execute()
    {
        $data = $this->getRequest()->getParams();
        $content = $this->getInsertImageData->getImageContent(
            $data['filename'],
            $data['force_static_path'],
            $data['as_is'],
            isset($data['store']) ? (int)$data['store'] : null
        );

        $size = $data['force_static_path'] ? $this->getInsertImageData->getFileSize($content) : 0;
        $type = $data['force_static_path'] ? $this->getInsertImageData->getMimeType($content) : '';
        return $this->resultJsonFactory->create()->setData(['content' => $content, 'size' => $size, 'type' => $type]);
    }
}
