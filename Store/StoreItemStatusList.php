<?php

require_once 'Store/Store.php';
require_once 'Store/StoreStatusList.php';
require_once 'Store/StoreItemStatus.php';
require_once 'SwatDB/SwatDBClassMap.php';

/**
 * A list of {@link StoreItemStatus} objects
 *
 * This list defines all item statuses and has methods to get a particular
 * status.
 *
 * By default, the following item statuses are defined:
 * - 'available'
 * - 'outofstock'
 *
 * If site code needs different or additional statuses it should subclass this
 * class and override the {@link StoreItemStatusList::getDefinedStatuses()}
 * method.
 *
 * @package   Store
 * @copyright 2007-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @see       StoreItemStatus
 */
class StoreItemStatusList extends StoreStatusList
{
	// {{{ private properties

	/**
	 * Static collection of available statuses for this class of status list
	 *
	 * @var array
	 */
	private static $defined_statuses = null;

	/**
	 * The item status list instance used for the singleton pattern
	 *
	 * @var StoreItemStatusList
	 *
	 * @see StoreItemStatusList::statuses()
	 */
	private static $instance;

	// }}}
	// {{{ public static function status()

	/**
	 * Convenience function to get a status by shortname without having to
	 * create a list instance
	 *
	 * Example usage:
	 *
	 * <code>
	 * $item->status = StoreItemStatusList::status('available');
	 * </code>
	 *
	 * @param string $status_shortname the shortname of the status to retrieve.
	 *
	 * @return StoreItemStatus the item status corresponding to the shortname
	 *                          or null if no such status exists.
	 */
	public static function status($status_shortname)
	{
		return self::statuses()->getByShortname($status_shortname);
	}

	// }}}
	// {{{ public static function statuses()

	/**
	 * Gets the list of defined item statuses
	 *
	 * Example usage:
	 *
	 * <code>
	 * foreach (StoreItemStatusList::statuses() as $status) {
	 *     echo $status->title, "\n";
	 * }
	 * </code>
	 *
	 * @return StoreItemStatusList the list of item statuses.
	 */
	public static function statuses()
	{
		if (self::$instance === null) {
			$list_class = SwatDBClassMap::get('StoreItemStatusList');
			self::$instance = new $list_class();
		}
		return self::$instance;
	}

	// }}}
	// {{{ protected function getDefinedStatuses()

	/**
	 * Gets an array of defined item statuses for this class of list
	 *
	 * Subclasses are encoraged to override this method to change the default
	 * set of item statuses or to provide additional statuses.
	 *
	 * @return array an array of {@link StoreItemStatus} objects representing
	 *                all defined item statuses for this class of list.
	 */
	protected function getDefinedStatuses()
	{
		if (self::$defined_statuses === null) {
			self::$defined_statuses = array();

			$status_class = SwatDBClassMap::get('StoreItemStatus');

			$available_status =
				new $status_class(0, 'available', Store::_('In-stock'));

			$out_of_stock_status =
				new $status_class(1, 'outofstock', Store::_('Out-of-stock'));

			self::$defined_statuses[] = $available_status;
			self::$defined_statuses[] = $out_of_stock_status;
		}

		return self::$defined_statuses;
	}

	// }}}
}

?>
