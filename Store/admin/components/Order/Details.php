<?php

require_once 'Admin/pages/AdminIndex.php';
require_once 'Admin/exceptions/AdminNotFoundException.php';

require_once 'Swat/SwatTableStore.php';
require_once 'Swat/SwatDetailsStore.php';
require_once 'SwatDB/SwatDB.php';

require_once 'Store/StoreTotalRow.php';
require_once 'Store/StoreShippingAddressCellRenderer.php';
require_once 'SwatDB/SwatDBClassMap.php';
require_once 'Store/dataobjects/StoreOrder.php';

/**
 * Details page for Orders
 *
 * @package   Store
 * @copyright 2006-2009 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
abstract class StoreOrderDetails extends AdminPage
{
	// {{{ protected properties

	protected $id;
	protected $order;

	/**
	 * If we came from an account page, this is the id of the account.
	 * Otherwise it is null.
	 *
	 * @var integer
	 */
	protected $account;

	/**
	 * @var string
	 */
	protected $ui_xml = 'Store/admin/components/Order/details.xml';

	// }}}

	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		parent::initInternal();

		$this->ui->mapClassPrefixToPath('Store', 'Store');
		$this->ui->loadFromXML($this->ui_xml);

		$this->id = SiteApplication::initVar('id');
		$this->account = SiteApplication::initVar('account');

		$this->getOrder();
	}

	// }}}
	// {{{ protected function getOrder()

	protected function getOrder()
	{
		if ($this->order === null) {
			$order_class = SwatDBClassMap::get('StoreOrder');
			$this->order = new $order_class();

			$this->order->setDatabase($this->app->db);

			if (!$this->order->load($this->id)) {
				throw new AdminNotFoundException(sprintf(
					Store::_('An order with an id of ‘%d’ does not exist.'),
					$this->id));
			}

			$instance_id = $this->app->getInstanceId();
			if ($instance_id !== null) {
				$order_instance_id = ($this->order->instance === null) ?
					null : $this->order->instance->id;

				if ($order_instance_id !== $instance_id)
					throw new AdminNotFoundException(sprintf(Store::_(
						'Incorrect instance for order ‘%d’.'), $this->id));
			}
		}

		return $this->order;
	}

	// }}}

	// build phase
	// {{{ protected function buildInternal()

	public function buildInternal()
	{
		parent::buildInternal();

		$details_frame = $this->ui->getWidget('details_frame');
		$details_frame->title = Store::_('Order');
		$details_frame->subtitle = $this->order->getTitle();

		// set default time zone on each date field
		$view = $this->ui->getWidget('order_details');
		foreach ($view->getDescendants('SwatDateCellRenderer') as $renderer) {
			$renderer->display_time_zone = $this->app->default_time_zone;
		}

		$this->buildOrderDetails();
		$this->buildMessages();
		$this->buildToolBar();
	}

	// }}}
	// {{{ protected function buildNavBar()

	protected function buildNavBar()
	{
		parent::buildNavBar();

		if ($this->account !== null) {
			// use account navbar
			$this->navbar->popEntry();
			$this->navbar->addEntry(new SwatNavBarEntry(
				Store::_('Customer Accounts'), 'Account'));

			$this->navbar->addEntry(new SwatNavBarEntry(
				$this->order->account->getFullname(),
				'Account/Details?id='.$this->order->account->id));

			$this->title = $this->order->account->getFullname();
		}

		$this->navbar->addEntry(new SwatNavBarEntry($this->order->getTitle()));
	}

	// }}}
	// {{{ protected function buildToolBar()

	protected function buildToolBar()
	{
		$toolbar = $this->ui->getWidget('details_toolbar');
		if ($this->account === null) {
			$toolbar->setToolLinkValues($this->id);
		} else {
			foreach ($toolbar->getToolLinks() as $tool_link) {
				$tool_link->link.= '&account=%s';
				$tool_link->value = array($this->id,
					$this->order->account->id);
			}
		}

		if ($this->order->cancel_date instanceof SwatDate) {
			if ($this->ui->hasWidget('cancel_order_link')) {
				$this->ui->getWidget('cancel_order_link')->visible = false;
			}
		}
	}

	// }}}
	// {{{ protected abstract function buildOrderDetails()

	protected abstract function buildOrderDetails();

	// }}}

	// finalize phase
	// {{{ public function finalize()

	public function finalize()
	{
		parent::finalize();
		$this->layout->addHtmlHeadEntry(new SwatStyleSheetHtmlHeadEntry(
			'packages/store/admin/styles/store-order-details.css',
			Store::PACKAGE_ID));
	}

	// }}}
}

?>
