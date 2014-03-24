<?php
/**
 * The news.
 * Sit back and relax, this might take a while.
 * History is NOT supported. Only the URLSegment is being tracked. This makes it a bit more simplistic.
 * 
 * @package News/blog module
 * @author Simon 'Sphere'
 * @method NewsHolderPage NewsHolderPages() this NewsItem belongs to
 * @method Image Impression() the Impression for this NewsItem
 * @method Comment Comment() Comments on this NewsItem
 * @method Renamed Renamed() changed URLSegments
 * @method SlideshowImage SlideshowImages() for the slideshow-feature
 * @method Tag Tags() Added Tags for this Item.
 */
class News extends DataObject { // implements IOGObject{ // optional for OpenGraph support

	private static $db = array(
		'Title' => 'Varchar(255)',
		/** Author might be handled via Member, but that's not useful if you want a non-member to post in his/her name */
		'Author' => 'Varchar(255)',
		'URLSegment' => 'Varchar(255)',
		'Synopsis' => 'Text',
		'Content' => 'HTMLText',
		'PublishFrom' => 'Date',
		'Tweeted' => 'Boolean(false)',
		'FBPosted' => 'Boolean(false)',
		'Live' => 'Boolean(true)',
		'Commenting' => 'Boolean(true)',
		/** This is for the external location of a link */
		'Type' => 'Enum("news,external,download","news")',
		'External' => 'Varchar(255)',
	);
	
	private static $has_one = array(
		'Impression' => 'Image',
		/** If you want to have a download-file */
		'Download' => 'File',
		/** Generic helper to have Author-specific pages */
		'AuthorHelper' => 'AuthorHelper',
	);
	
	private static $has_many = array(
		'Comments' => 'Comment',
		'Renamed' => 'Renamed',
		'SlideshowImages' => 'SlideshowImage',
	);
	
	private static $many_many = array(
		'Tags' => 'Tag',
	);
	
	private static $belongs_many_many = array(
		'NewsHolderPages' => 'NewsHolderPage',
	);
	
	private static $summary_fields = array();
	
	private static $searchable_fields = array();

	private static $default_sort = 'PublishFrom DESC';
	
	/**
	 * Set defaults. Commenting (show comments if allowed in siteconfig) is default to true.
	 * @var array $defaults. Commenting is true, SiteConfig overrides this!
	 */
	private static $defaults = array(
		'Commenting' => true,
	);
	
	/**
	 * On large databases, this is a small performance improvement.
	 * @var array $indexes.
	 */
	private static $indexes = array(
		'URLSegment' => true,
	);
	
	/**
	 * 
	 * @param array|null $record This will be null for a new database record.  Alternatively, you can pass an array of
	 * field values.  Normally this contructor is only used by the internal systems that get objects from the database.
	 * @param boolean $isSingleton This this to true if this is a singleton() object, a stub for calling methods.
	 *                             Singletons don't have their defaults set.
	 * @param News $model The model we're instantiating.
	 */
	public function __construct($record = null, $isSingleton = false, $model = null) {
		parent::__construct($record, $isSingleton, $model);
		if(!$this->ID && Member::currentUser()) {
			$name =  Member::currentUser()->FirstName . ' ' . Member::currentUser()->Surname;
			$this->Author = $name;
		}
	}

	/**
	 * Define singular name translatable
	 * @return string Singular name
	 */
	public function singular_name() {
		if (_t('News.SINGULARNAME')) {
			return _t('News.SINGULARNAME');
		} else {
			return parent::singular_name();
		} 
	}
	
	/**
	 * Define plural name translatable
	 * @return string Plural name
	 */
	public function plural_name() {
		if (_t('News.PLURALNAME')) {
			return _t('News.PLURALNAME');
		} else {
			return parent::plural_name();
		}
	}

	/**
	 * Define sumaryfields;
	 * @return array $summaryFields
	 */
	public function summaryFields() {
		$summaryFields = parent::summaryFields();
		$summaryFields = array_merge(
			$summaryFields, 
			array(
				'Title' => _t('News.TITLE', 'Title'),
				'Author' => _t('News.AUTHOR', 'Author'),
				'PublishFrom' => _t('News.PUBLISH', 'Publish from'),
			)
		);
		return $summaryFields;
	}
	
	/**
	 * Define translatable searchable fields
	 * @return array $searchableFields translatable
	 */
	public function searchableFields(){
		$searchableFields = parent::searchableFields();
		unset($searchableFields['PublishFrom']);
		$searchableFields['Title'] = array(
				'field'  => 'TextField',
				'filter' => 'PartialMatchFilter',
				'title'  => _t('News.TITLE','Title')
			);
		$searchableFields['Author'] = array(
			'field'  => 'TextField',
			'filter' => 'PartialMatchFilter',
			'title'  => _t('News.AUTHOR','Author')
		);
		return $searchableFields;
	}
	
	/**
	 * Free guess on what this button does.
	 * @return string Link to this object.
	 */
	public function Link($action = 'show/') {
		if ($Page = $this->NewsHolderPages()->first()) {
			return($Page->Link($action).$this->URLSegment);
		}
		return false;
	}

	/**
	 * This is quite handy, for meta-tags and such.
	 * @param string $action The added URLSegment, the actual function that'll return the news.
	 * @return string Link. To the item. (Yeah, I'm super cereal here)
	 */
	public function AbsoluteLink($action = 'show/'){
		if($Page = $this->Link($action)){
			return(Director::absoluteURL($Page));
		}		
	}

	/**
	 * The holder-page ID should be set if translatable, otherwise, we just select the first available one.
	 * The NewsHolderPage should NEVER be doubled.
	 * @todo slim down. Not everything here needs to be in onBeforeWrite.
	 */
	public function onBeforeWrite(){
		parent::onBeforeWrite();
		if(!class_exists('Translatable') || !$this->NewsHolderPages()->count()){
			$page = NewsHolderPage::get()->first();
			$this->NewsHolderPages()->add($page);
		}
		if(!$this->Type || $this->Type == ''){
			$this->Type = 'news';
		}
		/** Set PublishFrom to today to prevent errors with sorting. New since 2.0, backward compatible. */
		if(!$this->PublishFrom){
			$this->PublishFrom = date('Y-m-d');
		}
		/**
		 * Make sure the link is valid.
		 */
		if(substr($this->External,0,4) != 'http' && $this->External != ''){
			$this->External = 'http://'.$this->External;
		}
		if (!$this->URLSegment || ($this->isChanged('Title') && !$this->isChanged('URLSegment'))){
			if($this->ID > 0){
				$Renamed = new Renamed();
				$Renamed->OldLink = $this->URLSegment;
				$Renamed->NewsID = $this->ID;
				$Renamed->write();
			}
			$this->URLSegment = singleton('SiteTree')->generateURLSegment($this->Title);
			if(strpos($this->URLSegment, 'page-') === false){
				$nr = 1;
				while($this->LookForExistingURLSegment($this->URLSegment)){
					$this->URLSegment .= '-'.$nr++;
				}
			}
		}
		$this->Author = trim($this->Author);
		$author = AuthorHelper::get()->filter('OriginalName', trim($this->Author));
		if($author->count() == 0){
			$author = AuthorHelper::create();
			$author->OriginalName = trim($this->Author);
			$author->write();
		}
		else{
			$author = $author->first();
		}
		$this->AuthorID = $author->ID;
	}
	
	public function onAfterWrite(){
		parent::onAfterWrite();
		$siteConfig = SiteConfig::current_site_config();
		/**
		 * This is related to another module of mine.
		 * Check it at my repos: Silverstripe-Social.
		 * It auto-tweets your new Newsitem. If the TwitterController exists ofcourse.
		 * It doesn't auto-tweet if the publish-date is in the future. Also, it won't tweet when it's that date!
		 * @todo refactor this to a facebook/twitter oAuth method that a dev spent more time on developing than I did on my Social-module.
		 */
		if(class_exists('TwitterController')){
			if($this->Live && $this->PublishDate <= date('Y-m-d') && !$this->Tweeted && $siteConfig->TweetOnPost){
				$this->Tweeted = true;
				$this->write();
			}
		}
	}

	/**
	 * test whether the URLSegment exists already on another Newsitem
	 * @return boolean URLSegment already exists yes or no.
	 */
	public function LookForExistingURLSegment($URLSegment) {
		return(News::get()->filter(
				array("URLSegment" => $URLSegment)
			)->exclude(
				array("ID" => $this->ID)
			)->count() != 0);
	}
	
	public function getComments() {
		return $this->Comments()->filter(array('AkismetMarked' => 0));
	}

	/**
	 * Get the year this object is created.
	 * @return string $yearItems String of 4 numbers representing the year
	 */
	public function getYearCreated(){
		$yearItems = date('Y', strtotime($this->PublishFrom));
		return $yearItems;
	}

	/**
	 * Get the month this object is published
	 * @return string $monthItems double-digit representation of the month this object was published.
	 */
	public function getMonthCreated(){
		$monthItems = date('F', strtotime($this->PublishFrom));
		return $monthItems;
	}

        /**
         * Why oh why does $Date.Nice still not use i18n::get_date_format()??
	 * // If I recall correctly, this is a known issue with i18n class.
	 * @todo Fix this. It bugs out, for example, English notation(?) is MMM d Y, the three M make it go JunJunJun 1, 2013. BAD!
	 * Temporary fix: Forced to use d-m-Y
         * @return string
         */
        public function getPublished() {
		return date('d-m-Y', strtotime($this->PublishFrom));
		$format = i18n::get_date_format();
		return $this->dbObject('PublishFrom')->Format($format);
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
		return(Permission::checkMember($member, 'CMS_ACCESS_NewsAdmin') || $this->Live == 1);
	}
	
}
