<?php
/**
 * Tagging for your news, so you can categorize everything and optionally even create a tagcloud.
 * In the Holderpage, there's an option for the tags to view everything by tag.
 *
 * @author Simon 'Sphere' Erkelens
 * @package News/Blog module
 * @todo implement translations?
 * @method Impression Image() Impressionimage for this tag
 * @method News News() NewsItems this tag belongs to.
 */
class Tag extends DataObject {
	
	/** @var array $db database-fields */
	private static $db = array(
		'Title' => 'Varchar(255)',
		'Description' => 'HTMLText',
		'URLSegment' => 'Varchar(255)',
		'Locale' => 'Varchar(10)', // NOT YET SUPPORTED (I think)
		'SortOrder' => 'Int',
	);
	
	/** @var array $has_one relationships. */
	private static $has_one = array(
		'Impression' => 'Image',
	);
	
	/** @var array $belongs_many_many of belongings */
	private static $belongs_many_many = array(
		'News' => 'News',
	);

	/**
	 * CMS seems to ignore this unless sortable is enabled.
	 * Input appreciated.
	 * @var string $default_sort sortorder of this object.
	 */
	private static $default_sort = 'SortOrder ASC';
	
	/**
	 * Create indexes.
	 * @var array $indexes Index for the database
	 */
	private static $indexes = array(
		'URLSegment' => true,
	);
	
	/**
	 * Define singular name translatable
	 * @return string Singular name
	 */
	public function singular_name() {
		if (_t('Tag.SINGULARNAME')) {
			return _t('Tag.SINGULARNAME');
		} else {
			return parent::singular_name();
		} 
	}
	
	/**
	 * Define plural name translatable
	 * @return string Plural name
	 */
	public function plural_name() {
		if (_t('Tag.PLURALNAME')) {
			return _t('Tag.PLURALNAME');
		} else {
			return parent::plural_name();
		}   
	}
	
	/**
	 * @todo fix sortorder
	 * @return FieldList $fields Fields that are editable.
	 */
	public function getCMSFields() {
		/** Setup new Root Fieldlist */
		$fields = FieldList::create(TabSet::create('Root'));
		/** Add the fields */
		$fields->addFieldsToTab(
			'Root', // To what tab
			Tab::create(
				'Main', // Name
				_t('Tag.MAIN', 'Main'), // Title
				/** Fields */
				$text = TextField::create('Title', _t('Tag.TITLE', 'Title')),
				$html = HTMLEditorField::create('Description', _t('Tag.DESCRIPTION', 'Description')),
				$uplo = UploadField::create('Impression', _t('Tag.IMPRESSION', 'Impression image'))
			)
		);
		return($fields);
	}
	
	/**
	 * The holder-page ID should be set if translatable, otherwise, we just select the first available one. 
	 * @todo I still have to fix that translatable, remember? ;)
	 * @todo support multiple HolderPages?
	 */
	public function onBeforeWrite(){
		parent::onBeforeWrite();
		if (!$this->URLSegment || ($this->isChanged('Title') && !$this->isChanged('URLSegment'))){
			$this->URLSegment = singleton('SiteTree')->generateURLSegment($this->Title);
			if(strpos($this->URLSegment, 'page-') === false){
				$nr = 1;
				while($this->LookForExistingURLSegment($this->URLSegment)){
					$this->URLSegment .= '-'.$nr++;
				}
			}
		}
	}
	
	/**
	 * test whether the URLSegment exists already on another tag
	 * @return boolean if urlsegment already exists yes or no.
	 */
	public function LookForExistingURLSegment($URLSegment) {
		return(Tag::get()->filter(array("URLSegment" => $URLSegment))->exclude(array("ID" => $this->ID))->count() != 0);
	}

	/**
	 * Free guess on what this button does.
	 * @return string Link to this object.
	 */
	public function Link($action = 'tag/') {
		if ($Page = NewsHolderPage::get()->first()) {
			return($Page->Link($action).$this->URLSegment);
		}
	}

	/**
	 * This is quite handy, for meta-tags and such.
	 * @param string $action The added URLSegment, the actual function that'll return the tag.
	 * @return string Link. To the item. (Yeah, I'm super cereal here)
	 */
	public function AbsoluteLink($action = 'tag/'){
		if ($Page = NewsHolderPage::get()->first()) {
			return(Director::absoluteURL($Page->Link($action)). $this->URLSegment);
		}		
	}
	
	/**
	 * Permissions
	 */
	public function canCreate($member = null) {
		return(Permission::checkMember($member, 'CMS_ACCESS_NewsAdmin'));
	}

	public function canEdit($member = null) {
		return(Permission::checkMember($member, 'CMS_ACCESS_NewsAdmin'));
	}

	public function canDelete($member = null) {
		return(Permission::checkMember($member, 'CMS_ACCESS_NewsAdmin'));
	}

	public function canView($member = null) {
		return true;
	}
}
