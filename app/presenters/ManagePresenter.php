<?php

namespace NetteAddons;

use NetteAddons\Model\Addon,
	NetteAddons\Model\Addons,
	NetteAddons\Model\AddonVersion,
	NetteAddons\Model\AddonUpdater,
	NetteAddons\Model\IAddonImporter;
use Nette\Http\Session,
	Nette\Http\SessionSection;



final class ManagePresenter extends BasePresenter
{
	/** @var SessionSection */
	private $session;

	/** @var AddonUpdater */
	private $updater;

	/** @var Addons */
	private $addons;

	/**
	 * @var string
	 * @persistent
	 */
	public $token;

	/** @var Addon from the session. */
	private $addon;

	/** @var \Nette\Database\Table\ActiveRow Low-level database row. */
	private $addonRow;



	public function setContext(AddonUpdater $updater, Addons $addons, Session $session)
	{
		$this->updater = $updater;
		$this->addons = $addons;
		$this->session = $session->getSection('NetteAddons.ManagePresenter');
	}



	protected function startup()
	{
		parent::startup();

		if (!$this->user->isLoggedIn()) {
			$this->flashMessage('Please sign in to continue.');
			$this->redirect('Homepage:');
		}

		$this->restoreAddon();
		bd($this->addon);
	}



	/*************** Session storage ****************/


	/**
	 * Generates a new token for the wizzard.
	 */
	private function generateToken()
	{
		$this->token = base_convert(md5(lcg_value()), 16, 36);
	}



	/**
	 * Gets the session key for the addon stored under the current token.
	 *
	 * If there is no token, triggers generation of a new one.
	 * @return string
	 */
	private function getSessionKey()
	{
		if ($this->token === NULL) {
			$this->generateToken();
		}
		return "addon-$this->token";
	}



	/**
	 * Stores the addon object into the session.
	 */
	protected function storeAddon()
	{
		$this->session[$this->getSessionKey()] = $this->addon;
	}


	/**
	 * Restores the addon object from session.
	 */
	protected function restoreAddon()
	{
		if ($this->token !== NULL && isset($this->session[$this->getSessionKey()])) {
			$this->addon = $this->session[$this->getSessionKey()];
		}
	}


	protected function removeStoredAddon()
	{
		$this->addon = NULL;
		unset($this->session[$this->getSessionKey()]);
	}




	/*************** Addon creation ****************/


	/**
	 * Creates a new form for basic addon info.
	 * @return AddAddonForm
	 */
	protected function createComponentAddAddonForm()
	{
		$form = new AddAddonForm();
		$form->onSuccess[] = callback($this, 'addAddonFormSubmitted');

		if ($this->addon !== NULL) {
			$form->setAddonDefaults($this->addon);
		}

		return $form;
	}



	/**
	 * Handles the new addon form submission.
	 * @param \NetteAddons\AddAddonForm $form
	 */
	public function addAddonFormSubmitted(AddAddonForm $form)
	{
		$values = $form->getValues();

		if ($this->addon === NULL) {
			$this->addon = new Addon();
		}
		$this->addon->name = $values->name;
		$this->addon->shortDescription = $values->shortDescription;
		$this->addon->description = $values->description;
		$this->addon->demo = $values->demo;
		$this->addon->user = $this->getUser()->getIdentity();

		if ($this->addon->composerName === NULL) {
			$this->addon->buildComposerName();
		}

		if ($this->addons->findOneBy(array('composer_name' => $this->addon->composerName)) !== FALSE) {
			$message = 'Addon with same composer package already exists. ';
			if ($this->addon->repository) {
				$message .= 'Please specify another package to import.';
				$this->flashMessage($message);
				$this->redirect('add');
			} else {
				$message .= 'Please specify another addon name.';
				$form->addError($message);
				return;
			}
		}

		$this->updater->update($this->addon);
		$this->storeAddon();

		$this->flashMessage('Addon created.');

		if ($this->addon->repository) {
			$this->redirect('versionImport');

		} else {
			$this->redirect('versionCreate');
		}
	}




	/*************** Addon import ****************/

	protected function createComponentImportAddonForm()
	{
		$form = new ImportAddonForm();

		$form->onSuccess[] = callback($this, 'importAddonFormSubmitted');
		return $form;
	}


	public function importAddonFormSubmitted(ImportAddonForm $form)
	{
		$values = $form->getValues();
		$importer = $this->getContext()->createRepositoryImporter($values->url);
		$this->addon = $importer->import();
		if (!isset($this->addon->repository)) {
			$this->addon->repository = \NetteAddons\Model\GitHub\Repository::normalizeUrl($values->url);
		}
		$this->storeAddon();

		$this->addon->user = $this->getUser()->getIdentity();

		$this->flashMessage('Imported addon.');
		$this->redirect('create');
	}


	/*************** Create a new version ****************/

	public function actionVersionCreate($id = NULL)
	{
		if ($id !== NULL) {
			$this->addon = Addon::fromActiveRow($this->addons->findOneBy(array('id' => $id)));
			$this->addon->user = $this->getUser()->getIdentity();
		}
	}

	protected function createComponentAddVersionForm()
	{
		$form = new AddVersionForm();

		$form->onSuccess[] = callback($this, 'addVersionFormSubmitted');
		return $form;
	}


	public function addVersionFormSubmitted(AddVersionForm $form)
	{
		$values = $form->getValues();

		$version = new AddonVersion();
		$version->version = $values->version;
		$version->license = $values->license;
		$this->addon->versions[] = $version;
		$this->storeAddon();
		$this->updater->update($this->addon);

		$this->flashMessage('Version created.');
		if (($id = $this->getParameter('id')) === NULL) {
			$this->redirect('Detail:', $id);
		} else {
			$this->redirect('finish');
		}
	}



	/*************** Import versions ****************/

	public function handleImportVersions()
	{
		$importer = $this->getContext()->createRepositoryImporter($this->addon->repository);
		$this->addon->versions = $importer->importVersions();
		$this->storeAddon();
		$this->redirect('finish');
	}



	/*************** Finish the addon creation ****************/

	public function actionFinish()
	{
		if ($this->addon !== NULL) {
			$this->addon->user = $this->getUser()->getIdentity();
			$row = $this->updater->update($this->addon);
			$this->removeStoredAddon();
			$this->flashMessage('Addon sucessfuly saved.');
			$this->redirect('Detail:', $row->id);
		} else {
			$this->redirect('create');
		}
	}



	/*************** Addon editing ****************/

	public function actionEdit($id)
	{
		if (($this->addonRow = $this->addons->findOneBy(array('id' => $id))) === FALSE) {
			throw new \Nette\Application\BadRequestException('Invalid addon ID.');
		}
		$this->addon = Addon::fromActiveRow($this->addonRow);
	}


	protected function createComponentEditAddonForm()
	{
		$form = new EditAddonForm();
		$form->setAddonDefaults($this->addon);
		$form->onSuccess[] = callback($this, 'editAddonFormSubmitted');
		return $form;
	}


	public function editAddonFormSubmitted(EditAddonForm $form)
	{
		$values = $form->getValues();

		$this->addonRow->name = $values->name;
		$this->addonRow->short_description = $values->shortDescription;
		$this->addonRow->description = $values->description;
		$this->addonRow->demo = $values->demo;
		$this->addonRow->update();

		$this->flashMessage('Addon saved.');
		$this->redirect('Detail:', $this->addonRow->id);
	}

}
