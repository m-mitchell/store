<?php

require_once 'Store/pages/StoreAccountPage.php';
require_once 'Store/dataobjects/StoreAccountPaymentMethod.php';
require_once 'Store/dataobjects/StorePaymentType.php';
require_once 'Store/StoreUI.php';
require_once 'Store/StoreClassMap.php';
require_once 'Swat/SwatDate.php';

/**
 * Page to allow customers to add or edit payment methods on their account
 *
 * @package   Store
 * @copyright 2006-2007 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class StoreAccountPaymentMethodEditPage extends StoreAccountPage
{
	// {{{ protected properties

	/**
	 * @var string
	 */
	protected $ui_xml = 'Store/pages/account-payment-method-edit.xml';

	protected $ui;
	protected $id;

	// }}}
	// {{{ public function __construct()

	public function __construct(SiteApplication $app, SiteLayout $layout,
		$id = null)
	{
		parent::__construct($app, $layout);
		$this->id = intval($id);

		if ($this->id === 0)
			$this->id = null;
	}

	// }}}

	// init phase
	// {{{ public function init()

	public function init()
	{
		parent::init();

		$this->ui = new StoreUI();
		$this->ui->loadFromXML($this->ui_xml);

		$form = $this->ui->getWidget('edit_form');
		$form->action = $this->source;

		$this->ui->init();
	}

	// }}}
	// {{{ private function findPaymentMethod()

	/**
	 * @return StoreAccountPaymentMethod
	 */
	private function findPaymentMethod()
	{
		$account = $this->app->session->account;

		if ($this->id === null) {
			// create a new payment method
			$class_map = StoreClassMap::instance();
			$class = $class_map->resolveClass('StoreAccountPaymentMethod');
			$payment_method = new $class();
		} else {
			// edit existing payment method
			$payment_method = $account->payment_methods->getByIndex($this->id);

			// go back to account page if payment type is disabled
			$payment_type = $payment_method->payment_type;
			if (!$payment_type->isAvailableInRegion($this->app->getRegion()))
				$this->app->relocate('account');
		}

		if ($payment_method === null)
			throw new SiteNotFoundException(
				sprintf('A payment method with an id of ‘%d’ does not exist.',
				$this->id));

		return $payment_method;
	}

	// }}}

	// process phase
	// {{{ public function process()

	public function process()
	{
		parent::process();

		$type_list = $this->ui->getWidget('payment_type');
		$type_list->process();

		if ($type_list->value !== null) {
			$class_map = StoreClassMap::instance();
			$class_name = $class_map->resolveClass('StorePaymentType');
			$payment_type = new $class_name();
			$payment_type->setDatabase($this->app->db);
			$payment_type->load($type_list->value);
			$this->ui->getWidget('card_inception')->required =
				$payment_type->hasInceptionDate();

			$this->ui->getWidget('card_issue_number')->required =
				$payment_type->hasIssueNumber();
		}

		$form = $this->ui->getWidget('edit_form');
		$form->process();

		if ($form->isProcessed()) {
			if (!$form->hasMessage()) {
				$payment_method = $this->findPaymentMethod();
				$this->updatePaymentMethod($payment_method);

				if ($this->id === null) {
					$this->app->session->account->payment_methods->add(
						$payment_method);

					$this->addMessage('add', $payment_method);
				} elseif ($payment_method->isModified()) {
					$this->addMessage('update', $payment_method);
				}

				$this->app->session->account->save();
				$this->app->relocate('account');
			}
		}
	}

	// }}}
	// {{{ protected function updatePaymentMethod()

	/**
	 * Updates an account payment method's properties from form values
	 *
	 * @param StoreAccountPaymentMethod $payment_method
	 */
	protected function updatePaymentMethod(
		StoreAccountPaymentMethod $payment_method)
	{
		$payment_method->payment_type =
			$this->ui->getWidget('payment_type')->value;

		if ($this->id === null)
			$payment_method->setCreditCardNumber(
				$this->ui->getWidget('credit_card_number')->value);

		$payment_method->card_issue_number =
			$this->ui->getWidget('card_issue_number')->value;

		$payment_method->credit_card_expiry =
			$this->ui->getWidget('credit_card_expiry')->value;

		$payment_method->card_inception =
			$this->ui->getWidget('card_inception')->value;

		$payment_method->credit_card_fullname =
			$this->ui->getWidget('credit_card_fullname')->value;
	}

	// }}}
	// {{{ protected function getMessageText()

	protected function getMessageText($text)
	{
		switch ($text) {
		case 'add':
			return Store::_('One payment method has been added.');
		case 'update':
			return Store::_('One payment method has been updated.');
		default:
			return $text;
		}
	}

	// }}}
	// {{{ private function addMessage()

	private function addMessage($text, StorePaymentMethod $payment_method)
	{
		$text = $this->getMessageText($text);

		ob_start();
		$payment_method->display();
		$payment_display = ob_get_clean();

		$message = new SwatMessage($text, SwatMessage::NOTIFICATION);
		$message->secondary_content = $payment_display;
		$message->content_type = 'text/xml';
		$this->app->messages->add($message);
	}

	// }}}

	// build phase
	// {{{ public function build()

	public function build()
	{
		parent::build();

		$form = $this->ui->getWidget('edit_form');
		$form->action = $this->source;

		$this->layout->addHtmlHeadEntrySet(
			$this->ui->getRoot()->getHtmlHeadEntrySet());

		$type_where_clause = 'enabled = true';
		$type_join_clause = sprintf('inner join PaymentTypeRegionBinding on '.
			'payment_type = id and region = %s',
			$this->app->db->quote($this->app->getRegion()->id, 'integer'));

		if (!$form->isProcessed()) {
			if ($this->id === null) {
				$this->ui->getWidget('credit_card_fullname')->value =
					$this->app->session->account->fullname;
			} else {
				$payment_method = $this->findPaymentMethod();
				$this->setWidgetValues($payment_method);

				if ($payment_method !== null) {
					// allow disabled types
					$type_where_clause = sprintf('id = %s',
						$this->app->db->quote(
							$payment_method->payment_type->id, 'integer'));

					$join_clause = '';
				}
			}
		}

		$this->buildLabels();

		if ($this->id !== null) {
			$this->ui->getWidget('credit_card_number')->visible = false;
			$this->ui->getWidget('credit_card_number_last4')->visible = true;
			$this->ui->getWidget('payment_type')->show_blank = false;
		} else {
			$this->ui->getWidget('credit_card_number')->visible = true;
			$this->ui->getWidget('credit_card_number_last4')->visible = false;
		}

		$type_flydown = $this->ui->getWidget('payment_type');
		$types_sql = sprintf('select id, title from PaymentType
			%s where %s order by title',
			$type_join_clause, $type_where_clause);

		$types = SwatDB::query($this->app->db, $types_sql);
		foreach ($types as $type)
			$type_flydown->addOption(
				new SwatOption($type->id, $type->title));

		$this->layout->startCapture('content');
		$this->ui->display();
		$this->layout->endCapture();
	}

	// }}}
	// {{{ protected function buildLabels()

	protected function buildLabels()
	{
		if ($this->id === null) {
			$this->layout->navbar->createEntry(
				Store::_('Add a New Payment Method'));

			$this->layout->data->title = Store::_('Add a New Payment Method');
		} else {
			$this->layout->navbar->createEntry(
				Store::_('Edit a Payment Method'));

			$this->ui->getWidget('submit_button')->title =
				Store::_('Update Payment Method');

			$this->layout->data->title = Store::_('Edit a Payment Method');
		}
	}

	// }}}
	// {{{ protected function setWidgetValues()

	protected function setWidgetValues(
		StoreAccountPaymentMethod $payment_method)
	{
		$this->ui->getWidget('payment_type')->value =
			$payment_method->payment_type->id;

		$this->ui->getWidget('credit_card_number_last4')->content =
			StorePaymentType::formatCreditCardNumber(
				$payment_method->credit_card_last4,
				$payment_method->payment_type->getCreditCardMaskedFormat());

		$this->ui->getWidget('card_issue_number')->value =
			$payment_method->card_issue_number;

		$this->ui->getWidget('credit_card_expiry')->value =
			$payment_method->credit_card_expiry;

		if (!$this->ui->getWidget('credit_card_expiry')->isValid()) {
			$expiry = $this->ui->getWidget('credit_card_expiry');

			$content = sprintf(Store::_('The expiry date that was entered '.
				'(%s) is in the past. Please enter an updated date.'),
				$expiry->value->format(SwatDate::DF_CC_MY));

			$message = new SwatMessage($content, SwatMessage::WARNING);
			$expiry->addMessage($message);

			$expiry->value = null;
		}

		$this->ui->getWidget('card_inception')->value =
			$payment_method->card_inception;

		$this->ui->getWidget('credit_card_fullname')->value =
			$payment_method->credit_card_fullname;
	}

	// }}}
}

?>
