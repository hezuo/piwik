<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik
 * @package Piwik
 */
namespace Piwik\Tracker;

use Piwik\ArchiveProcessor\Rules;
use Piwik\CacheFile;
use Piwik\Config;
use Piwik\Option;
use Piwik\Piwik;
use Piwik\Tracker;

/**
 * Simple cache mechanism used in Tracker to avoid requesting settings from mysql on every request
 *
 * @package Piwik
 * @subpackage Tracker
 */
class Cache
{
    /**
     * Public for tests only
     * @var CacheFile
     */
    static public $trackerCache = null;

    static protected function getInstance()
    {
        if (is_null(self::$trackerCache)) {
            $ttl = Config::getInstance()->Tracker['tracker_cache_file_ttl'];
            self::$trackerCache = new CacheFile('tracker', $ttl);
        }
        return self::$trackerCache;
    }

    /**
     * Returns array containing data about the website: goals, URLs, etc.
     *
     * @param int $idSite
     * @return array
     */
    static function getCacheWebsiteAttributes($idSite)
    {
        $idSite = (int)$idSite;

        $cache = self::getInstance();
        if (($cacheContent = $cache->get($idSite)) !== false) {
            return $cacheContent;
        }

        Tracker::initCorePiwikInTrackerMode();

        // save current user privilege and temporarily assume super user privilege
        $isSuperUser = Piwik::isUserIsSuperUser();
        Piwik::setUserIsSuperUser();

        $content = array();
        
        /**
         * This event is called to get the details of a site by its ID. It can be used to
         * add custom attributes for the website to the Tracker cache.
         * 
         * **Example**
         * 
         *     public function getSiteAttributes($content, $idSite)
         *     {
         *         $sql = "SELECT info FROM " . Common::prefixTable('myplugin_extra_site_info') . " WHERE idsite = ?";
         *         $content['myplugin_site_data'] = Db::fetchOne($sql, array($idSite));
         *     }
         * 
         * @param array &$content List of attributes.
         * @param int $idSite The site ID.
         */
        Piwik::postEvent('Site.getSiteAttributes', array(&$content, $idSite));

        // restore original user privilege
        Piwik::setUserIsSuperUser($isSuperUser);

        // if nothing is returned from the plugins, we don't save the content
        // this is not expected: all websites are expected to have at least one URL
        if (!empty($content)) {
            $cache->set($idSite, $content);
        }
        return $content;
    }

    /**
     * Clear general (global) cache
     */
    static public function clearCacheGeneral()
    {
        self::getInstance()->delete('general');
    }

    /**
     * Returns contents of general (global) cache.
     * If the cache file tmp/cache/tracker/general.php does not exist yet, create it
     *
     * @return array
     */
    static public function getCacheGeneral()
    {
        $cache = self::getInstance();
        $cacheId = 'general';
        $expectedRows = 3;
        if (($cacheContent = $cache->get($cacheId)) !== false
            && count($cacheContent) == $expectedRows
        ) {
            return $cacheContent;
        }

        Tracker::initCorePiwikInTrackerMode();
        $cacheContent = array(
            'isBrowserTriggerEnabled' => Rules::isBrowserTriggerEnabled(),
            'lastTrackerCronRun'      => Option::get('lastTrackerCronRun'),
        );

        /**
         * Triggered before the general tracker cache is saved to disk. This event can be
         * used to add extra content to the cace.
         * 
         * Data that is used during tracking but is expensive to compute/query should be
         * cached so as not to slow the tracker down. One example of such data are options
         * that are stored in the piwik_option table. Requesting data on each tracking
         * request means an extra unnecessary database query on each visitor action.
         * 
         * **Example**
         * 
         *     public function setTrackerCacheGeneral(&$cacheContent)
         *     {
         *         $cacheContent['MyPlugin.myCacheKey'] = Option::get('MyPlugin_myOption');
         *     }
         * 
         * @param array &$cacheContent Array of cached data. Each piece of data must be
         *                             mapped by name.
         */
        Piwik::postEvent('Tracker.setTrackerCacheGeneral', array(&$cacheContent));
        self::setCacheGeneral($cacheContent);
        return $cacheContent;
    }

    /**
     * Store data in general (global cache)
     *
     * @param mixed $value
     * @return bool
     */
    static public function setCacheGeneral($value)
    {
        $cache = self::getInstance();
        $cacheId = 'general';
        $cache->set($cacheId, $value);
        return true;
    }

    /**
     * Regenerate Tracker cache files
     *
     * @param array|int $idSites Array of idSites to clear cache for
     */
    static public function regenerateCacheWebsiteAttributes($idSites = array())
    {
        if (!is_array($idSites)) {
            $idSites = array($idSites);
        }
        foreach ($idSites as $idSite) {
            self::deleteCacheWebsiteAttributes($idSite);
            self::getCacheWebsiteAttributes($idSite);
        }
    }

    /**
     * Delete existing Tracker cache
     *
     * @param string $idSite (website ID of the site to clear cache for
     */
    static public function deleteCacheWebsiteAttributes($idSite)
    {
        $idSite = (int)$idSite;
        self::getInstance()->delete($idSite);
    }

    /**
     * Deletes all Tracker cache files
     */
    static public function deleteTrackerCache()
    {
        self::getInstance()->deleteAll();
    }
}
