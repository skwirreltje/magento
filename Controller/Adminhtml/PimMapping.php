<?php


namespace Skwirrel\Pim\Controller\Adminhtml;

abstract class PimMapping extends \Magento\Backend\App\Action
{

    const ADMIN_RESOURCE = 'Skwirrel_Pim::top_level';
    protected $_coreRegistry;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Registry $coreRegistry
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Registry $coreRegistry
    ) {
        $this->_coreRegistry = $coreRegistry;
        parent::__construct($context);
    }

    /**
     * Init page
     *
     * @param \Magento\Backend\Model\View\Result\Page $resultPage
     * @return \Magento\Backend\Model\View\Result\Page
     */
    public function initPage($resultPage)
    {
        $resultPage->setActiveMenu(self::ADMIN_RESOURCE)
            ->addBreadcrumb(__('Skwirrel'), __('Skwirrel'))
            ->addBreadcrumb(__('Pimmapping'), __('Pimmapping'));
        return $resultPage;
    }
}
