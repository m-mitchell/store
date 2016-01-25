<?php

require_once 'Site/pages/SiteSiteMapPage.php';

/**
 * @package   Store
 * @copyright 2007-2016 silverorange
 */
class StoreSiteMapPage extends SiteSiteMapPage
{
	// {{{ protected function queryArticles()

	protected function queryArticles()
	{
		$articles = parent::queryArticles();
		$articles->setRegion($this->app->getRegion());

		return $articles;
	}

	// }}}
}

?>
