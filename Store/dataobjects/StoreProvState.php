<?php

require_once 'SwatDB/SwatDBDataObject.php';
require_once 'Store/dataobjects/StoreCountry.php';

/**
 * A province/state data object
 *
 * @package   Store
 * @copyright 2006-2007 silverorange
 */
class StoreProvState extends SwatDBDataObject
{
	// {{{ public properties

	/**
	 * Unique identifier of this province or state
	 *
	 * @var integer
	 */
	public $id;

	/**
	 * User visible title of this province or state
	 *
	 * @var string
	 */
	public $title;

	/**
	 * A two letter abbreviation used to identify this province of state
	 *
	 * This is also used for displaying addresses.
	 *
	 * @var string
	 */
	public $abbreviation;

	// }}}
	// {{{ protected function init()

	protected function init()
	{
		$this->table = 'ProvState';
		$this->id_field = 'integer:id';

		$this->registerInternalProperty('country',
			SwatDBClassMap::get('StoreCountry'));
	}

	// }}}
}

?>
