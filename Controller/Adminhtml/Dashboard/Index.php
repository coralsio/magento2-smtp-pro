<?php
declare(strict_types=1);

namespace Corals\SMTP\Controller\Adminhtml\Dashboard;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    const ADMIN_RESOURCE = 'Corals_SMTP::dashboard';
    
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
        $resultPage->setActiveMenu('Corals_SMTP::dashboard');
        $resultPage->getConfig()->getTitle()->prepend(__('SMTP Dashboard & Statistics'));
        
        return $resultPage;
    }
}