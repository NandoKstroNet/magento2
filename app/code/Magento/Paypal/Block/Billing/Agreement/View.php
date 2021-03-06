<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @copyright   Copyright (c) 2014 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Magento\Paypal\Block\Billing\Agreement;

/**
 * Customer account billing agreement view block
 */
class View extends \Magento\Framework\View\Element\Template
{
    /**
     * Payment methods array
     *
     * @var array
     */
    protected $_paymentMethods = array();

    /**
     * Billing Agreement instance
     *
     * @var \Magento\Paypal\Model\Billing\Agreement
     */
    protected $_billingAgreementInstance = null;

    /**
     * Related orders collection
     *
     * @var \Magento\Sales\Model\Resource\Order\Collection
     */
    protected $_relatedOrders = null;

    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry = null;

    /**
     * @var \Magento\Sales\Model\Resource\Order\CollectionFactory
     */
    protected $_orderCollectionFactory;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var \Magento\Sales\Model\Order\Config
     */
    protected $_orderConfig;

    /**
     * @var \Magento\Paypal\Helper\Data
     */
    protected $_helper;

    /**
     * @var \Magento\Paypal\Model\Resource\Billing\Agreement
     */
    protected $_agreementResource;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Sales\Model\Resource\Order\CollectionFactory $orderCollectionFactory
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Sales\Model\Order\Config $orderConfig
     * @param \Magento\Paypal\Helper\Data $helper
     * @param \Magento\Paypal\Model\Resource\Billing\Agreement $agreementResource
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Sales\Model\Resource\Order\CollectionFactory $orderCollectionFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Sales\Model\Order\Config $orderConfig,
        \Magento\Paypal\Helper\Data $helper,
        \Magento\Paypal\Model\Resource\Billing\Agreement $agreementResource,
        array $data = array()
    ) {
        $this->_helper = $helper;
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->_customerSession = $customerSession;
        $this->_orderConfig = $orderConfig;
        $this->_coreRegistry = $registry;
        $this->_agreementResource = $agreementResource;
        parent::__construct($context, $data);
        $this->_isScopePrivate = true;
    }

    /**
     * Retrieve related orders collection
     *
     * @return \Magento\Sales\Model\Resource\Order\Collection
     */
    public function getRelatedOrders()
    {
        if (is_null($this->_relatedOrders)) {
            $billingAgreement = $this->_getBillingAgreementInstance();
            $billingAgreementId = $billingAgreement ? $billingAgreement->getAgreementId() : 0;
            $this->_relatedOrders = $this->_orderCollectionFactory->create()->addFieldToSelect(
                '*'
            )->addFieldToFilter(
                'customer_id',
                (int)$this->_customerSession->getCustomerId()
            )->addFieldToFilter(
                'state',
                array('in' => $this->_orderConfig->getVisibleOnFrontStates())
            )->setOrder(
                'created_at',
                'desc'
            );
            $this->_agreementResource->addOrdersFilter($this->_relatedOrders, $billingAgreementId);
        }
        return $this->_relatedOrders;
    }

    /**
     * Retrieve order item value by key
     *
     * @param \Magento\Sales\Model\Order $order
     * @param string $key
     * @return string
     */
    public function getOrderItemValue(\Magento\Sales\Model\Order $order, $key)
    {
        $escape = true;
        switch ($key) {
            case 'order_increment_id':
                $value = $order->getIncrementId();
                break;
            case 'created_at':
                $value = $this->formatDate($order->getCreatedAt(), 'short', true);
                break;
            case 'shipping_address':
                $value = $order->getShippingAddress() ? $this->escapeHtml(
                    $order->getShippingAddress()->getName()
                ) : __(
                    'N/A'
                );
                break;
            case 'order_total':
                $value = $order->formatPrice($order->getGrandTotal());
                $escape = false;
                break;
            case 'status_label':
                $value = $order->getStatusLabel();
                break;
            case 'view_url':
                $value = $this->getUrl('sales/order/view', array('order_id' => $order->getId()));
                break;
            default:
                $value = $order->getData($key) ? $order->getData($key) : __('N/A');
                break;
        }
        return $escape ? $this->escapeHtml($value) : $value;
    }

    /**
     * Set pager
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $pager = $this->getLayout()->createBlock(
            'Magento\Theme\Block\Html\Pager'
        )->setCollection(
            $this->getRelatedOrders()
        )->setIsOutputRequired(
            false
        );
        $this->setChild('pager', $pager);
        $this->getRelatedOrders()->load();

        return $this;
    }

    /**
     * Return current billing agreement.
     *
     * @return \Magento\Paypal\Model\Billing\Agreement|null
     */
    protected function _getBillingAgreementInstance()
    {
        if (is_null($this->_billingAgreementInstance)) {
            $this->_billingAgreementInstance = $this->_coreRegistry->registry('current_billing_agreement');
        }
        return $this->_billingAgreementInstance;
    }

    /**
     * Load available billing agreement methods
     *
     * @return array
     */
    protected function _loadPaymentMethods()
    {
        if (!$this->_paymentMethods) {
            foreach ($this->_helper->getBillingAgreementMethods() as $paymentMethod) {
                $this->_paymentMethods[$paymentMethod->getCode()] = $paymentMethod->getTitle();
            }
        }
        return $this->_paymentMethods;
    }

    /**
     * Set data to block
     *
     * @return string
     */
    protected function _toHtml()
    {
        $this->_loadPaymentMethods();
        $this->setBackUrl($this->getUrl('*/billing_agreement/'));
        $billingAgreement = $this->_getBillingAgreementInstance();
        if ($billingAgreement) {
            $this->setReferenceId($billingAgreement->getReferenceId());

            $this->setCanCancel($billingAgreement->canCancel());
            $this->setCancelUrl(
                $this->getUrl(
                    '*/billing_agreement/cancel',
                    array('_current' => true, 'payment_method' => $billingAgreement->getMethodCode())
                )
            );

            $paymentMethodTitle = $billingAgreement->getAgreementLabel();
            $this->setPaymentMethodTitle($paymentMethodTitle);

            $createdAt = $billingAgreement->getCreatedAt();
            $updatedAt = $billingAgreement->getUpdatedAt();
            $this->setAgreementCreatedAt($createdAt ? $this->formatDate($createdAt, 'short', true) : __('N/A'));
            if ($updatedAt) {
                $this->setAgreementUpdatedAt($this->formatDate($updatedAt, 'short', true));
            }
            $this->setAgreementStatus($billingAgreement->getStatusLabel());
        }

        return parent::_toHtml();
    }
}
