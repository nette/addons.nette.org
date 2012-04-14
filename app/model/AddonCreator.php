<?php

namespace NetteAddons\Model;

use Nette;



/**
 * @author Filip Procházka <filip.prochazka@kdyby.org>
 */
class AddonCreator extends Nette\Object
{

	/**
	 * @var Addons
	 */
	private $addons;

	/**
	 * @var Tags
	 */
	private $tags;

	/**
	 * @var AddonVersions
	 */
	private $versions;

	/**
	 * @var VersionDependencies
	 */
	private $dependencies;



	/**
	 * @param \NetteAddons\Model\Addons $addons
	 * @param \NetteAddons\Model\Tags $tags
	 * @param \NetteAddons\Model\AddonVersions $versions
	 * @param \NetteAddons\Model\VersionDependencies $dependencies
	 */
	public function __construct(Addons $addons, Tags $tags, AddonVersions $versions, VersionDependencies $dependencies)
	{
		$this->addons = $addons;
		$this->tags = $tags;
		$this->versions = $versions;
		$this->dependencies = $dependencies;
	}



	/**
	 * @param \NetteAddons\Model\Addon $addon
	 */
	public function create(Addon $addon)
	{
		throw new Nette\NotImplementedException;
	}


}
