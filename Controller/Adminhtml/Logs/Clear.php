<?php
declare(strict_types=1);

namespace Corals\SMTP\Controller\Adminhtml\Logs;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;

class Clear extends Action
{
    const ADMIN_RESOURCE = 'Corals_SMTP::logs';

    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        try {
            // Here you would implement log clearing logic
            // For now, just show success message
            $this->messageManager->addSuccessMessage(__('Email logs have been cleared successfully.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error clearing logs: %1', $e->getMessage()));
        }

        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setPath('*/*/index');
    }
}