<?php
/**
 * Part of the Platform application.
 *
 * NOTICE OF LICENSE
 *
 * Licensed under the 3-clause BSD License.
 *
 * This source file is subject to the 3-clause BSD License that is
 * bundled with this package in the LICENSE file.  It is also available at
 * the following URL: http://www.opensource.org/licenses/BSD-3-Clause
 *
 * @package    Platform
 * @version    1.0
 * @author     Cartalyst LLC
 * @license    BSD License (3-clause)
 * @copyright  (c) 2011 - 2012, Cartalyst LLC
 * @link       http://cartalyst.com
 */

namespace Extensions;

use Bundle;
use Closure;
use DB;
use Exception;
use Laravel\CLI\Command;
use Laravel\Database\Schema;
use Str;

/**
 * Extension Manager class.
 *
 * @author Ben Corlett
 */
class Manager
{

	/**
	 * Array of started Platform Extensions
	 *
	 * @var array
	 */
	protected $extensions = array();

	/**
	 * An array of extensions that are exempt
	 * from being treated like normal extensions.
	 */
	protected $exempt = array('extensions', 'installer');

	/**
	 * Starts all installed extensions with Platform
	 *
	 * @return  Manager
	 */
	public function start_extensions()
	{
		// Get all enabled extensions
		$extensions = $this->enabled();

		// Loop through and start every extension
		foreach ($extensions as $extension)
		{
			$this->start($extension);
		}

		return $this;
	}

	/**
	 * Starts an extension.
	 *
	 * @param   string  $name
	 * @param   mixed   $value
	 */
	public function start($extension)
	{
		// We might have been given the slug
		// of an extension to start
		if ( ! $extension instanceof Extension)
		{
			$model = Extension::find(function($query) use ($extension)
			{
				return $query->where('slug', '=', $extension);
			});

			if ($model === null)
			{
				throw new Exception("Platform Extension [$extension] doesn't exist in database.");
			}

			$extension = $model;
		}

		// If the extension is already started
		if (array_key_exists($extension->slug, $this->extensions))
		{
			return $this;
		}

		// Load extension info
		$info = $this->info($extension->slug);

		// Register the bundle with Laravel
		array_key_exists('bundles', $info) and Bundle::register($extension->slug, $info['bundles']);

		// Start the bundle
		Bundle::start($extension->slug);

		// Register global routes
		if (array_key_exists('global_routes', $info))
		{
			// Check we've been given a closure
			if ( ! $info['global_routes'] instanceof Closure)
			{
				throw new Exception("'global_routes' must be a function / closure in [$file]");

			}

			$info['global_routes']();
		}

		// Register listeners
		if (array_key_exists('listeners', $info))
		{
			// Check we've been given a closure
			if ( ! $info['listeners'] instanceof Closure)
			{
				throw new Exception("'listeners' must be a function / closure in [$file]");

			}

			$info['listeners']();
		}

		return $this;
	}

	/**
	 * Installs an extension by the given slug.
	 *
	 * @param   string  $slug
	 * @return  Extension
	 */
	public function install($slug, $enable = false)
	{
		// Get extension info
		$info = $this->info($slug);

		// Create a new model instance.
		$extension = new Extension(array(
			'name'        => isset($info['info']['name']) ? $info['info']['name'] : '',
			'slug'        => isset($info['info']['slug']) ? $info['info']['slug'] : '',
			'version'     => isset($info['info']['version']) ? $info['info']['version'] : '',
			'author'      => isset($info['info']['author']) ? $info['info']['author'] : '',
			'description' => isset($info['info']['description']) ? $info['info']['description'] : '',
			'is_core'     => isset($info['info']['is_core']) ? $info['info']['is_core'] : '',
			'enabled'     => (int) $enable,
		));
		$extension->save();

		// We need to start the extension, just in case
		// the migrations that we're about to run require
		// classes that are in the extension. Starting
		// the extension will allow the classes to be autoloaded.
		// An example of this is in the "menus" extension, it
		// uses the "menus" model.
		$this->start($extension->slug);

		// Resolves core tasks.
		require_once path('sys').'cli/dependencies'.EXT;

		/**
		 * @todo remove when my pull request gets accepted
		 */
		ob_start();

		// Run extensions migration. This will prepare
		// the table we need to install the core extensions
		Command::run(array('migrate', $extension->slug));

		/**
		 * @todo remove when my pull request gets accepted
		 */
		ob_end_clean();

		return $extension;
	}

	/**
	 * Enables an extension.
	 *
	 * @param   int  $id
	 * @return  Extension
	 */
	public function enable($id)
	{
		$extension = Extension::find($id);

		if ($extension === null)
		{
			throw new Exception('Platform extension doesn\'t exist.');
		}

		$extension->enabled = 1;
		$extension->save();

		return $extension;
	}

	/**
	 * Disables an extension.
	 *
	 * @param   int  $id
	 * @return  Extension
	 */
	public function disable($id)
	{
		$extension = Extension::find($id);

		if ($extension === null)
		{
			throw new Exception('Platform extension doesn\'t exist.');
		}

		$extension->enabled = 0;
		$extension->save();

		return $extension;
	}

	/**
	 * Prepares the Platform database for extensions by insuring that
	 * the extensions table is installed in addition to the migrations
	 * table.
	 *
	 * @return  void
	 */
	public function prepare_db_for_extensions()
	{
		/**
		 * @todo remove when my pull request gets accepted
		 */
		ob_start();

		// Resolves core tasks.
		require_once path('sys').'cli/dependencies'.EXT;

		// Check for the migrations table
		try
		{
			DB::table('laravel_migrations')->count();
		}
		catch (Exception $e)
		{
			Command::run(array('migrate:install'));
		}

		// Check for the extensions table. The reason
		// this isn't in a migration is simply
		try
		{
			DB::table('extensions')->count();
		}
		catch (Exception $e)
		{
			Schema::create('extensions', function($table)
			{
				$table->increments('id')->unsigned();
				$table->string('name', 50);
				$table->string('slug', 50)->unique();
				$table->string('author', 50)->nullable();
				$table->text('description')->nullable();
				$table->text('version', 5);
				$table->boolean('is_core')->nullable();
				$table->boolean('enabled');
			});
		}

		// Just incase the install process got interrupted, start
		// extensions
		$this->start_extensions();

		/**
		 * @todo remove when my pull request gets accepted
		 */
		ob_end_clean();

		return;
	}

	/**
	 * Returns all installed extensions as an array
	 * of Extensions\Extenion models.
	 *
	 * @return  array
	 */
	public function installed($condition = null)
	{
		return Extension::all($condition);
	}

	/**
	 * Returns all enabled extensions as an array
	 * of Extensions\Extenion models.
	 *
	 * @param   Closure  $condition
	 * @return  array
	 */
	public function enabled($condition = null)
	{
		return Extension::all(function($query) use ($condition)
		{
			$query->where('enabled', '=', 1);

			if ($condition instanceof Closure)
			{
				$query = $condition($query);
			}

			return $query;
		});
	}

	/**
	 * Returns all disabled extensions as an array
	 * of Extensions\Extenion models.
	 *
	 * @param   Closure  $condition
	 * @return  array
	 */
	public function disabled($condition = null)
	{
		return Extension::all(function($query) use ($condition)
		{
			$query->where('enabled', '=', 0);

			if ($condition instanceof Closure)
			{
				$query = $condition($query);
			}

			return $query;
		});
	}

	/**
	 * Returns a simple array of uninstalled
	 * extensions, with numberic keys, and
	 * where the slug (which is
	 * the folder name of the extension) is the
	 * value.
	 *
	 * @param   Closure  $condition
	 * @return  array
	 */
	public function uninstalled($condition = null)
	{
		// Firstly, get all installed extensions
		$results = $this->installed(function($query) use ($condition)
		{
			// We only want to select the slug
			$query->select('slug');

			// Check if we have a closure provided as
			// a condition to this function
			if ($condition instanceof Closure)
			{
				$query = $condition($query);
			}

			return $query;
		});

		// Build a basic array of installed extensions
		$installed = array();
		foreach ($results as $result)
		{
			$installed[] = $result->slug;
		}

		// Build an array of uninstalled extensions
		$uninstalled = array();
		foreach ($this->extensions_directories() as $extension)
		{
			// Get our extension slug - always
			// matches the folder name.
			$slug = Str::lower(basename($extension));

			! in_array($slug, $installed) and $uninstalled[] = $slug;
		}

		return $uninstalled;
	}

	/**
	 * Returns an array of cascaded extension directories
	 * based on the order of arguments provided.
	 *
	 * Extensions are parsed through the order in which they're
	 * passed to this function.
	 * 
	 * @param   mixed
	 * @return  array
	 */
	protected function cascade_extesions_directories()
	{
		// Fallbacks
		$extensions      = array();
		$directories     = array();
		$extension_slugs = array();

		foreach (func_get_args() as $extensions)
		{
			foreach ($extensions as $extension)
			{
				$extension = dirname($extension);

				// Cache the directory slug
				$slug = basename($extension);

				// Only add if it's not already added and it's not
				// in the exempt list
				if ( ! in_array($slug, $extension_slugs) and ! in_array($slug, $this->exempt))
				{
					$directories[]     = $extension;
					$extension_slugs[] = $slug;
				}
			}
		}

		return $directories;
	}

	/**
	 * Returns an array of extensions' directories.
	 *
	 * @todo Determine order of extensions in the groups. For example,
	 *       "Platform" will be loaded last
	 *
	 * @return  array
	 */
	public function extensions_directories()
	{
		$grouped_extensions   = glob(path('bundle').'*'.DS.'*'.DS.'extension'.EXT, GLOB_NOSORT);
		$top_level_extensions = glob(path('bundle').'*'.DS.'extension'.EXT, GLOB_NOSORT);

		return $this->cascade_extesions_directories($top_level_extensions, $grouped_extensions);
	}

	public function sort_dependencies(&$extensions = array())
	{
		// Array of extensions dependencies, where
		// the key is the slug of the extension
		// and the value is an array of extension slugs
		// on which that extension depends.
		$extensions_dependencies = array();

		foreach ($extensions as $extension)
		{
			$info = $this->info($extension);

			if ($dependencies = array_get($info, 'dependencies') and is_array($dependencies))
			{
				$extensions_dependencies[$extension] = $dependencies;
			}
			else
			{
				$extensions_dependencies[$extension] = array();
			}
		}

		$extensions = Dependency::sort($extensions_dependencies);

		return $extensions;
	}

	public function find_extension_file($slug)
	{
		// We'll search the root dir first
		$files = glob(path('bundle').$slug.DS.'extension'.EXT);

		if (empty($files))
		{
			// We couldn't find the extension file in the first path, so we'll try the 2nd
			$files = glob(path('bundle').'*'.DS.$slug.DS.'extension'.EXT);
		}

		return ( ! empty($files)) ? $files[0] : false;
	}

	public function info($slug)
	{
		$file = $this->find_extension_file($slug);

		if ( ! $file)
		{
			throw new Exception("Platform Extension [$slug] doesn't exist.");
		}

		return require $file;
	}

}
