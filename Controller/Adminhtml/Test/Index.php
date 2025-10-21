<?php
declare(strict_types=1);

namespace Corals\SMTP\Controller\Adminhtml\Test;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    const ADMIN_RESOURCE = 'Corals_SMTP::test';
    
    protected PageFactory $resultPageFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Corals_SMTP::test');
        $resultPage->getConfig()->getTitle()->prepend(__('Test Email Configuration'));
        
        return $resultPage;
    }
}