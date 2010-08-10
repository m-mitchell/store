<?php

require_once 'Swat/SwatControl.php';
require_once 'Swat/SwatString.php';
require_once 'Swat/SwatTableStore.php';
require_once 'Swat/SwatDetailsStore.php';
require_once 'Swat/SwatUI.php';
require_once 'Swat/SwatHtmlHeadEntrySet.php';
require_once 'Store/dataobjects/StoreProduct.php';

/**
 * Control to display and process items on a product page
 *
 * @package   Store
 * @copyright 2007 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreItemsView extends SwatControl
{
	// {{{ public properties

	public $ui_xml = 'Store/items-view.xml';
	public $ui;

	// }}}
	// {{{ protected properties

	/**
	 * The product displayed by this items view
	 *
	 * @var StoreProductWrapper
	 */
	protected $product;

	protected $default_quantity;

	protected $has_description = false;

	/**
	 * @var boolean
	 */
	protected $has_same_part_count = null;

	// }}}
	// {{{ public function setProduct()

	/**
	 * Sets the product of this view
	 *
	 * @param StoreProductWrapper $product the product of this items view.
	 */
	public function setProduct(StoreProduct $product)
	{
		$this->product = $product;
	}

	// }}}
	// {{{ public function setSource()

	/**
	 * Sets the page source for this view's form action
	 *
	 * @param string $source The page source
	 */
	public function setSource($source)
	{
		$this->source = $source;
	}

	// }}}
	// {{{ public function init()

	public function init()
	{
		parent::init();

		$this->ui = new SwatUI();
		$this->ui->loadFromXML($this->ui_xml);

		$items_form = $this->ui->getWidget('form');
		$items_form->action = $this->source;

		if ($this->ui->hasWidget('quantity')) {
			$quantity = $this->ui->getWidget('quantity');
			$this->default_quantity = $quantity->value;
		}

		$view = $this->ui->getWidget('items_view');

		$this->ui->init();
		$view->model = $this->getItemTableStore($view);
	}

	// }}}
	// {{{ public function process()

	public function process()
	{
		parent::process();
		$this->ui->process();
	}

	// }}}
	// {{{ public function getCartEntries()

	public function getCartEntries()
	{
		$form = $this->ui->getWidget('form');
		$entries = array();

		if ($form->isProcessed()) {
			$renderer = $this->getQuantityRenderer();
			foreach ($renderer->getClonedWidgets() as $id => $widget) {
				if (!$renderer->hasMessage($id) && $widget->value > 0) {
					$cart_entry = $this->createCartEntry($id, $widget->value);
					$entries[] = $cart_entry;

					// reset qauntity - not persistent
					$widget->value = $this->default_quantity;
				}
			}
		}

		return $entries;
	}

	// }}}
	// {{{ public function hasMessage()

	public function hasMessage()
	{
		$form = $this->ui->getWidget('form');
		return ($form->isProcessed() && $form->hasMessage());
	}

	// }}}
	// {{{ public function display()

	public function display()
	{
		parent::display();

		$view = $this->ui->getWidget('items_view');

		if (!$this->has_description)
			$view->getColumn('description_column')->visible = false;

		$this->ui->display();
	}

	// }}}
	// {{{ public function getHtmlHeadEntrySet()

	/**
	 * Gets the SwatHtmlHeadEntry objects needed by this view
	 *
	 * @return SwatHtmlHeadEntrySet the SwatHtmlHeadEntry objects needed by
	 *                               this view.
	 */
	public function getHtmlHeadEntrySet()
	{
		$set = parent::getHtmlHeadEntrySet();

		if ($this->isVisible()) {
			$set->addEntrySet($this->ui->getRoot()->getHtmlHeadEntrySet());
		}

		return $set;
	}

	// }}}
	// {{{ protected function createCartEntry()

	protected function createCartEntry($item_id, $quantity)
	{
		$cart_entry_class = SwatDBClassMap::get('StoreCartEntry');
		$cart_entry = new $cart_entry_class();
		$item = $this->product->items->getByIndex($item_id);
		$cart_entry->item = $item;
		$cart_entry->setQuantity($quantity);

		return $cart_entry;
	}

	// }}}
	// {{{ protected function getItemTableStore()

	protected function getItemTableStore(SwatTableView $view)
	{
		$store = new SwatTableStore();
		$last_sku = null;
		$tab_index = 1;

		foreach ($this->product->items as $item) {
			if ($item->isEnabled()) {
				$ds = $this->getItemDetailsStore($item);
				$ds->tab_index = $tab_index++;

				$ds->sku = ($last_sku === $item->sku) ?
					'' : $item->sku;

				$last_sku = $item->sku;
				$store->add($ds);

				if ($ds->is_available)
					$view->getRow('add_button')->title =
						Store::_('Add to Cart');
			}
		}

		$view->getRow('add_button')->tab_index = $tab_index;
		return $store;
	}

	// }}}
	// {{{ protected function getItemDetailsStore()

	protected function getItemDetailsStore(StoreItem $item)
	{
		$ds = new SwatDetailsStore($item);

		$ds->description = $this->getItemDescription($item);

		if ($ds->description != '')
			$this->has_description = true;

		$ds->is_available = $item->isAvailableInRegion();

		$ds->status = '';
		if (!$item->hasAvailableStatus())
			$ds->status = sprintf('<span class="item-status">%s</span>',
				$item->getStatus()->title);

		$ds->price = $item->getDisplayPrice();
		$ds->original_price = $item->getOriginalPrice();
		$ds->discount = $ds->original_price - $ds->price;
		$ds->is_on_sale = $item->isOnSale();
		$ds->savings = $item->getSavings();

		return $ds;
	}

	// }}}
	// {{{ protected function getItemDescription()

	protected function getItemDescription(StoreItem $item)
	{
		$parts = $item->getDescriptionArray();
		$description = array();

		if (isset($parts['description']))
			$description[] =
				SwatString::minimizeEntities($parts['description']);

		if (isset($parts['part_count']))
			$description[] =
				SwatString::minimizeEntities($parts['part_count']);

		return implode(' - ', $description);
	}

	// }}}
	// {{{ protected function hasSamePartCount()

	/**
	 * Gets whether all the items in the product have the same part count
	 *
	 * @return boolean true if all the items in the product have the same
	 *                  part count and false if they do not.
	 */
	protected function hasSamePartCount()
	{
		if ($this->has_same_part_count === null) {
			$this->has_same_part_count = true;

			// Clone to work around poor implementation of iterators in SwatDB
			// and PHP SPL. This iteration can happen inside another iteration
			// of the same recordset.
			$items = clone $this->product->items;
			$last_item = $items->getFirst();

			foreach ($items as $item) {
				if ($item->part_count !== $last_item->part_count ||
					$item->part_unit !== $last_item->part_unit) {
					$this->has_same_part_count = false;
					break;
				}
			}
		}

		return $this->has_same_part_count;
	}

	// }}}
	// {{{ protected function getQuantityRenderer()

	protected function getQuantityRenderer()
	{
		$view = $this->ui->getWidget('items_view');
		if ($view->hasSpanningColumn('quantity_column'))
			$column = $view->getSpanningColumn('quantity_column');
		else
			$column = $view->getColumn('quantity_column');

		return $column->getRenderer('quantity_renderer');
	}

	// }}}
}

?>
