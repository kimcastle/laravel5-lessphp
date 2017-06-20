<?php

namespace Kimcastle\Laravel5Less;

use lessc;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Cache\Repository as Cache;

class Less
{
    const RECOMPILE_ALWAYS = 'always';
    const RECOMPILE_CHANGE = 'change';
    const RECOMPILE_NONE = 'none';

    protected $config;
    protected $jobs;
    protected $modified_vars;
    protected $parsed_less;
    protected $recompiled;
    public static $cache_key = 'less_cache';

    public function __construct(Config $config, Cache $cache) {
        $this->config = $config;
        $this->cache = $cache;
        $this->fresh();
    }

    /**
     * Compile CSS
     * @param string $filename LESS filename without extension
     * @param array $options Compile options
     * @return bool true on success, false on failure
     */
    public function compile($filename, $options = array()) {
        $config = $this->prepareConfig($options);
        $input_path = $config['less_path'] . DIRECTORY_SEPARATOR . $filename . '.less';
        $output_path = $config['public_path'] . DIRECTORY_SEPARATOR . $filename . '.css';
        $parser = new \Less_Parser($config);
        $parser->parseFile($input_path, asset('/'));
        // Iterate through jobs
        foreach($this->jobs as $i => $job) {
            call_user_func_array(array($parser, array_shift($job)), $job);
        }
        $written = $this->writeCss($output_path, $parser->getCss());
        // Remove old cache files if successfully written
        if ($written === true) {
            $this->cleanCache();
        }
        return $written;
    }

    /**
     * Reset current jobs for initiating a new Less instance
     * @return \Less
     */
    public function fresh() {
        $this->jobs = array();
        $this->modified_vars = array();
        $this->parsed_less = '';
        $this->recompiled = false;
        return $this;
    }

    /**
     * Write CSS file to disk
     * @param  string $output_path CSS filepath
     * @param  string $css CSS
     * @return bool true on succes, false on failure
     */
    protected function writeCss($output_path, $css) {
        return file_put_contents($output_path, $css) !== false;
    }

    /**
     * Clean cache
     */
    protected function cleanCache() {
        \Less_Cache::CleanCache();
    }

    /**
     * * Recompile CSS if needed
     * @param string $filename CSS filename without extension
     * @param string $recompile CSS always (RECOMPILE_ALWAYS), when changed (RECOMPILE_CHANGE) or never (RECOMPILE_NONE)
     * @param array $options Extra compile options
     * @return bool true on recompiled, false when not
     * @throws \Exception
     */
    public function recompile($filename, $recompile = null, $options = array()) {
        if ($this->recompiled === true) {
            return false; // This instance is already recompiled. Recompile a new or the same instance using Less::fresh()
        }
        if (is_null($recompile)) {
            $recompile = config('less.less_recompile', self::RECOMPILE_ALWAYS);
        }
        $this->recompiled = false; // Default value
        switch($recompile) {
            case self::RECOMPILE_ALWAYS :
                $this->recompiled = $this->compile($filename, $options);
                break;
            case self::RECOMPILE_CHANGE :
                $config = $this->prepareConfig($options);
                $input_path = $config['less_path'] . DIRECTORY_SEPARATOR . $filename . '.less';
                $cache_key = $this->getCacheKey($filename);
                $cache_value = \Less_Cache::Get(array($input_path => asset('/')), $config, $this->modified_vars);
                if ($this->cache->get($cache_key) !== $cache_value || !empty($this->parsed_less)) {
                    $this->recompiled = $this->compile($filename, $options);
                    $this->cache->put($cache_key, $cache_value, \Less_Cache::$gc_lifetime);
                }
                break;
            case self::RECOMPILE_NONE :
            case null:
                // Do nothing obviously
                break;
            default:
                throw new \Exception('Unknown \'' . $recompile . '\' LESS_RECOMPILE setting');
        }
        return $this->recompiled;
    }

    /**
     * Get filename-based cache key
     * @param string $filename
     * @return  string Cache key
     */
    protected function getCacheKey($filename) {
        return self::$cache_key . '_' . $filename;
    }

    /**
     * Get configuration
     * @param array $options
     * @return array Less configuration
     */
    protected function prepareConfig($options = array()) {
        $defaults = array(
            'compress' => env('LESS_COMPRESS', false),
            'sourceMap' => env('LESS_SOURCEMAP', false),
            'cache_dir' => storage_path('framework/cache/lessphp'),
            'public_path' => public_path('css'),
            'less_path' => base_path('resources/assets/less'),
            // 'cache_method' => function() {}
        );
        return array_merge($defaults, $this->config->get('less', array()), $options);
    }

    /**
     * Append custom CSS/LESS to CSS resulting file
     * @param string $less
     * @return \Less
     */
    public function parse($less) {
        $this->jobs[] = array('parse', $less);
        $this->parsed_less .= $less . PHP_EOL;
        return $this;
    }

    /**
     * Set values of LESS variables
     * @param array|string $variables
     * @return \Less
     */
    public function modifyVars($variables) {
        if (is_string($variables)) {
            $variables = $this->parseVariables($variables);
        }
        $this->jobs[] = array('ModifyVars', $variables);
        $this->modified_vars = array_merge($this->modified_vars, $variables);
        return $this;
    }

    /**
     * Transform plain LESS "<property>: <value>;" into a workable array
     * @param string $less LESS
     * @return array
     **/
    public function parseVariables($less) {
        $variables = array();
        $properties = preg_split('/;\s+/', $less);
        foreach($properties as $property) {
            if (preg_match('/(?:(?<name>[^}:]+):?(?<value>[^};]+);?)/', $property, $matches)) {
                $variables[$matches['name']] = trim($matches['value']);
            }
        }
        return $variables;
    }

    /**
     * Return output CSS url. Recompile CSS as configured
     * @param $filename
     * @param bool $auto_recompile
     * @param array $options the config options
     * @return mixed
     * @throws \Exception
     */
    public function url($filename, $auto_recompile = false, $options = array()) {
        if ($auto_recompile) {
            $recompiled = $this->recompile($filename, null, $options);
        }
        $config = $this->prepareConfig($options);
        $css_path = $config['link_path'] . '/' . $filename . '.css';
        return asset($css_path);
    }
}
