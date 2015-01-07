<?php
/**
 * Provides an extension to limit subpages shown in sitetree,
 * adapted from: http://www.dio5.com/blog/limiting-subpages-in-silverstripe/
 *
 * Features:
 * - Configure page classes to hide under current page
 * 
 * Example from within a class:
 * <code>
 * class SubPageHolder extends Page {
 *		...
 *		static $extensions = array("ExcludeChildren");
 *		static $excluded_children = array('SubPage', 'Another');
 *		...
 * </code>
 * 
 * Or externally via _config.php:
 * 
 * <code>
 * 	Object::add_extension("BlogHolder", "ExcludeChildren");
 * 	Config::inst()->update("BlogHolder", "excluded_children", array("BlogEntry"));
 * </code>
 * 
 * @author Michael van Schaik, Restruct. <mic@restruct.nl>
 * @author Tim Klein, Dodat Ltd <$firstname@dodat.co.nz>
 * @package Hierarchy
 * @subpackage HideChildren
 */

class ExcludeChildren extends DataExtension {
	
	protected $hiddenChildren = array();

	public function getExcludedClasses(){
		$hiddenChildren = array();
		if ($configClasses = $this->owner->config()->get("excluded_children")) {
			foreach ($configClasses as $class) {
				$hiddenChildren = array_merge($hiddenChildren, array_values(ClassInfo::subclassesFor($class)));
			}
		}
		$this->hiddenChildren = $hiddenChildren; 
		return $this->hiddenChildren;
	}
	
	public function getFilteredChildren($staged){
		$action = Controller::curr()->getAction();
		if (in_array($action, array('treeview','getsubtree'))) {
			return $staged->exclude('ClassName', $this->getExcludedClasses());
		}
		return $staged;
		
		// Another interesting approach, limiting filtering to only the CMS:
		// get_class($controller) == "CMSPagesController"
		// && in_array($controller->getAction(), array("treeview", "listview", "getsubtree"));
	}

    public function stageChildren($showAll = false){
		$children = $this->hierarchyStageChildren($showAll);
		return $this->getFilteredChildren($children);
    }

    public function liveChildren($showAll = false, $onlyDeletedFromStage = false){
		$children = $this->hierarchyLiveChildren($showAll, $onlyDeletedFromStage);
		return $this->getFilteredChildren($children);
    }
	
	/**
	 * Duplicated & renamed from the Hierarchy::tageChildren() because we're overriding the original method:
	 * Return children from the stage site
	 * 
	 * @param showAll Inlcude all of the elements, even those not shown in the menus.
	 *   (only applicable when extension is applied to {@link SiteTree}).
	 * @return DataList
	 */
	public function hierarchyStageChildren($showAll = false) {
		$baseClass = ClassInfo::baseDataClass($this->owner->class);
		$staged = $baseClass::get()
			->filter('ParentID', (int)$this->owner->ID)
			->exclude('ID', (int)$this->owner->ID);
		if (!$showAll && $this->owner->db('ShowInMenus')) {
			$staged = $staged->filter('ShowInMenus', 1);
		}
		$this->owner->extend("augmentStageChildren", $staged, $showAll);
		return $staged;
	}
	
	/**
	 * Duplicated & renamed from the Hierarchy::liveChildren() because we're overriding the original method:
	 * Return children from the live site, if it exists.
	 * 
	 * @param boolean $showAll Include all of the elements, even those not shown in the menus.
	 *   (only applicable when extension is applied to {@link SiteTree}).
	 * @param boolean $onlyDeletedFromStage Only return items that have been deleted from stage
	 * @return SS_List
	 */
	public function hierarchyLiveChildren($showAll = false, $onlyDeletedFromStage = false) {
		if(!$this->owner->hasExtension('Versioned')) {
			throw new Exception('Hierarchy->liveChildren() only works with Versioned extension applied');
		}

		$baseClass = ClassInfo::baseDataClass($this->owner->class);
		$children = $baseClass::get()
			->filter('ParentID', (int)$this->owner->ID)
			->exclude('ID', (int)$this->owner->ID)
			->setDataQueryParam(array(
				'Versioned.mode' => $onlyDeletedFromStage ? 'stage_unique' : 'stage',
				'Versioned.stage' => 'Live'
			));
		
		if(!$showAll) $children = $children->filter('ShowInMenus', 1);

		return $children;
	}
	
}
