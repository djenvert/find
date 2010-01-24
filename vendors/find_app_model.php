<?php
/*
 * App Model custom find types
 * Copyright (c) 2009 Matt Curry
 * www.PseudoCoder.com
 * http://github.com/mcurry/find
 *
 * Thanks to Daniel Salazar for the inspiration on pagination support
 * http://code621.com/content/10/easy-pagination-using-matt-curry-s-custom-find-types
 *
 * Adds support for custom find methods (__findXX) and automatic caching.
 * Automatic caching will kick in when 'cache' is passed in the $options
 * array. 
 * If 'cache' is a string, then it will be used to generate the
 * cache name, which takes the format model_alias_cache_name.
 * If 'cache' is an array, then two arguments are valid: 'name', required,
 * and 'config', optional. 'name' is used as above, while 'config' 
 * determines the cache configuration to use - 'default' if not specified.
 *
 * @author      Matt Curry <matt@pseudocoder.com>
 * @license     MIT
 *
 */
 
class FindAppModel extends Model {
  function find($type, $options = array()) {
    $return = $this->_getCachedResults($options);
    if (!$return) {  
        $method = null;
        if(is_string($type)) {
            $method = sprintf('__find%s', Inflector::camelize($type));
        }
        if($method && method_exists($this, $method)) {
            $return = $this->{$method}($options);
      
            if($return === null && !empty($this->query['paginate'])) {
                unset($this->query['paginate']);
                $query = $this->query;
                $this->query = null;
                $return = $query;
            }
        } else {
            $args = func_get_args();
            $return = call_user_func_array(array('parent', 'find'), $args);
        }
        if ($this->useCache) {
                 Cache::write($this->cacheName, $return, $this->cacheConfig);
        }
    }
    return $return;
   
  }
  
  function beforeFind($query) {
    if(!empty($query['paginate'])) {
      $keys = array('fields', 'order', 'limit', 'page');
      foreach($keys as $key) {
        if($query[$key] === null || (is_array($query[$key]) && $query[$key][0] === null) ) {
          unset($query[$key]);
        }
      }
      
      $this->query = $query;
      return false;
    }
    
    return true;
  }
  
  function _getCachedResults($options) {
        $this->useCache = true;
        if (Configure::read('debug') > 0 || !isset($options['cache']) || $options['cache'] == false) {
            $this->useCache = false;
            return false;
        }

        if (is_string($options['cache'])) {
            $this->cacheName = $this->alias . '_' . $options['cache'];
        } else {
            if (!isset($options['cache']['name'])) {
                return false;
            }
            $this->cacheName = $this->alias . '_' . $options['cache']['name'];
            $this->cacheConfig = isset($options['cache']['config']) ? $options['cache']['config'] : 'default';
        }

        $results = Cache::read($this->cacheName, $this->cacheConfig);

        return $results;
    }
}
?>