<?php
/**
 *
 * License, TERMS and CONDITIONS
 *
 * This software is lisensed under the GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * Please read the license here : http://www.gnu.org/licenses/lgpl-3.0.txt
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * ATTRIBUTION REQUIRED
 * 4. All web pages generated by the use of this software, or at least
 * 	  the page that lists the recent questions (usually home page) must include
 *    a link to the http://www.lampcms.com and text of the link must indicate that
 *    the website's Questions/Answers functionality is powered by lampcms.com
 *    An example of acceptable link would be "Powered by <a href="http://www.lampcms.com">LampCMS</a>"
 *    The location of the link is not important, it can be in the footer of the page
 *    but it must not be hidden by style attibutes
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE FREEBSD PROJECT OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This product includes GeoLite data created by MaxMind,
 *  available from http://www.maxmind.com/
 *
 *
 * @author     Dmitri Snytkine <cms@lampcms.com>
 * @copyright  2005-2011 (or current year) ExamNotes.net inc.
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: @package_version@
 *
 *
 */


namespace Lampcms;


use Lampcms\String\HTMLStringParser;


/**
 *
 * Class responsible for adding a new question
 * to QUESTIONS collection as well as updating
 * all Tags-related collections as well as increasing
 * count of user questions and updating per-user tags.
 *
 * This class does everything that has to be done
 * when new questions is submitted, regardless of how
 * it was submitted. It accepts an object of type
 * SubmittedQuestion which may be sub-classed to work with
 * many different ways question can be submitted: web, api, email, etc.
 *
 * @author Dmitri Snytkine
 *
 */
class QuestionParser extends LampcmsObject
{

	/**
	 * Object of type SubmittedQuestion
	 * (or any sub-class of it)
	 *
	 * @var Object SubmittedQuestion
	 */
	protected $oSubmitted;

	/**
	 * New question object
	 * created
	 *
	 * @var object of type Question
	 */
	protected $oQuestion;

	protected $oCache;

	public function __construct(Registry $oRegistry){
		$this->oRegistry = $oRegistry;
		/**
		 * Need to instantiate Cache so that it
		 * will listen to event and unset some keys
		 */
		$this->oCache = $this->oRegistry->Cache;
		$this->oRegistry->registerObservers('INPUT_FILTERS');
	}

	/**
	 * Getter for submitted object
	 * This can be used from observer object
	 * like spam filter so that via oSubmitted
	 * it's possible to call getUserObject()
	 * and get user object of question submitter, then
	 * look at some personal stats like reputation score,
	 * usergroup, etc.
	 *
	 * @return object of type SubmittedQuestion
	 */
	public function getSubmitted(){

		return $this->oSubmitted;
	}

	/**
	 * Main entry method to start processing
	 * the submitted question
	 *
	 * @param object $o object SubmittedQuestion
	 */
	public function parse(SubmittedQuestion $o){

		$this->oSubmitted = $o;

		$this->makeQuestion()
		->addToSearchIndex()
		->addTags()
		->addUnansweredTags()
		->addRelatedTags()
		->addUserTags();

		d('cp parsing done, returning question');

		return $this->oQuestion;
	}


	/**
	 * Prepares data for the question object,
	 * creates the $this->oQuestion object
	 * and saves data to QUESTIONS collection
	 *
	 * @return object $this
	 *
	 * @throws QuestionParserException in case a filter (which is an observer)
	 * either throws a FilterException (or sub-class of it) OR just cancells event
	 *
	 */
	protected function makeQuestion(){

		$oTitle = $this->oSubmitted->getTitle()->htmlentities()->trim();

		$username = $this->oSubmitted->getUserObject()->getDisplayName();

		$aTags = $this->oSubmitted->getTagsArray();

		/**
		 * Must pass array('drop-proprietary-attributes' => false)
		 * otherwise tidy removes rel="code"
		 */
		$aEditorConfig = $this->oRegistry->Ini->getSection('EDITOR');
		$tidyConfig = ($aEditorConfig['ENABLE_CODE_EDITOR']) ? array('drop-proprietary-attributes' => false) : null;
		$oBody = $this->oSubmitted->getBody()->tidy($tidyConfig)->safeHtml()->asHtml();

		/**
		 *
		 * Now body is in html but we still need to run
		 * it through HTMLStringParser string in order
		 * to make clickable links and to
		 * make sure all links are nofollow
		 *
		 */
		$htmlBody = HTMLStringParser::factory($oBody)->parseCodeTags()->linkify()->importCDATA()->setNofollow()->hilightWords($aTags)->valueOf();
		d('after HTMLStringParser: '.$htmlBody);

		$uid = $this->oSubmitted->getUserObject()->getUid();
		$hash = hash('md5', strtolower($htmlBody.json_encode($aTags)));

		/**
		 * @todo can parse forMakrdown now but ideally
		 * parseMarkdown() would be done inside Utf8string
		 * as well as parseSmilies
		 *
		 * @todo later can also parse for smilies here
		 *
		 */
		$this->checkForDuplicate($uid, $hash);

		$username = $this->oSubmitted->getUserObject()->getDisplayName();
		$time = time();
		/**
		 *
		 * @var array
		 */
		$aData = array(
		'_id' => $this->oRegistry->Resource->create('QUESTION'),
		'title' => $oTitle->valueOf(),
		/*'title_hash' => hash('md5', strtolower(trim($title)) ),*/
		'b' => $htmlBody,
		'hash' => $hash,
		'intro' => $this->oSubmitted->getBody()->asPlainText()->truncate(150)->valueOf(),
		'url' => $this->oSubmitted->getTitle()->toASCII()->makeLinkTitle()->valueOf(),
		'i_words' => $this->oSubmitted->getBody()->asPlainText()->getWordsCount(),
		'i_uid' => $uid,
		'username' => $username,
		'ulink' => '<a href="'.$this->oSubmitted->getUserObject()->getProfileUrl().'">'.$username.'</a>',
		'avtr' => $this->oSubmitted->getUserObject()->getAvatarSrc(),
		'i_up' => 0,
		'i_down' => 0,
		'i_votes' => 0,
		'i_favs' => 0,
		'i_views' => 0,
		'a_tags' => $aTags,
		'a_title' => TitleTokenizer::factory($oTitle)->getArrayCopy(),
		'status' => 'unans',
		'tags_html' => \tplQtags::loop($aTags, false),
		'credits' => '',
		'i_ts' => $time,
		'hts' => date('F j, Y g:i a T'),
		'i_lm_ts' => $time,
		'i_ans' => 0,
		'ans_s' => 's',
		'v_s' => 's',
		'f_s' => 's',
		'ip' => $this->oSubmitted->getIP(),
		'app' => 'web',
		'i_flwrs' => 1 // initially question has 1 follower - its author
		);

		/**
		 * Submitted question object may provide
		 * extra elements to be added to aData array
		 * This is usually useful for parsing questions that
		 * came from external API, in which case the answered/unanswred
		 * status as well as number of answers is already known
		 *
		 * as well as adding 'credit' div
		 */
		$aExtraData = $this->oSubmitted->getExtraData();
		d('$aExtraData: '.print_r($aExtraData, 1));
		if(is_array($aExtraData) && !empty($aExtraData)){
			$aData = array_merge($aData, $aExtraData);
		}

		$this->oQuestion = new Question($this->oRegistry, $aData);

		/**
		 * Post onBeforeNewQuestion event
		 * and watch for filter either cancelling the event
		 * or throwing FilterException (prefferred way because
		 * a specific error message can be passed in FilterException
		 * this way)
		 *
		 * In either case we throw QuestionParserException
		 * Controller that handles the question form should be ready
		 * to handle this exception and set the form error using
		 * message from exception. This way the error will be shown to
		 * the user right on the question form while question form's data
		 * is preserved in form.
		 *
		 * Filter can also modify the data in oQuestion before
		 * it is saved. This is convenient, we can even set different
		 * username, i_uid if we want to 'post as alias'
		 */
		try {
			$oNotification = $this->oRegistry->Dispatcher->post($this->oQuestion, 'onBeforeNewQuestion');
			if($oNotification->isNotificationCancelled()){
				throw new QuestionParserException('Sorry, we are unable to process your question at this time.');
			}
		} catch (FilterException $e){
			e('Got filter exteption: '.$e->getFile().' '.$e->getLine().' '.$e->getMessage().' '.$e->getTraceAsString());
			throw new QuestionParserException($e->getMessage());
		}

		/**
		 * Do ensureIndexes() now and not before we are sure that we even going
		 * to add a new question.
		 */
		$this->ensureIndexes();

		$this->oQuestion->insert();
		$this->followQuestion();

		$this->oRegistry->Dispatcher->post($this->oQuestion, 'onNewQuestion');

		return $this;
	}


	/**
	 * Adds Question to array of user's followed
	 * questions
	 * and adds user details to array of Question's followers
	 *
	 * @return object $this
	 */
	protected function followQuestion(){

		/**
		 * For consistant behaviour it is
		 * Best is to go through FollowManager and don't
		 * do this manually
		 */
		FollowManager::factory($this->oRegistry)->followQuestion($this->oRegistry->Viewer, $this->oQuestion);

		return $this;
	}


	/**
	 * Ensure indexes in all collections involved
	 * in storing question data
	 *
	 * @return object $this
	 */
	protected function ensureIndexes(){
		$quest = $this->oRegistry->Mongo->QUESTIONS;
		$quest->ensureIndex(array('i_sticky' => 1));
		$quest->ensureIndex(array('i_ts' => 1));
		$quest->ensureIndex(array('i_votes' => 1));
		$quest->ensureIndex(array('i_ans' => 1));
		$quest->ensureIndex(array('a_tags' => 1));
		$quest->ensureIndex(array('i_uid' => 1));
		$quest->ensureIndex(array('hash' => 1));
		$quest->ensureIndex(array('a_title' => 1));

		/**
		 * Need ip index to use flood filter by ip
		 * and to quickly find all posts by ip
		 * in case of deleting a spam.
		 *
		 */
		$quest->ensureIndex(array('ip' => 1));

		/**
		 * Index a_f_q in USERS (array of followed question ids)
		 * @todo move this to when the user is created!
		 */
		$this->oRegistry->Mongo->USERS->ensureIndex(array('a_f_q' => 1));

		return $this;
	}


	/**
	 * Check to see if same user has already posted
	 * exact same question
	 *
	 * @todo translate the error message
	 *
	 * @param int $uid
	 * @param string $hash hash of question body
	 */
	protected function checkForDuplicate($uid, $hash){
		$a = $this->oRegistry->Mongo->QUESTIONS->findOne(array('i_uid' => $uid, 'hash' => $hash ));
		if(!empty($a)){
			$err = 'You have already asked exact same question  <span title="'.$a['hts'].'" class="ts" rel="time">on '.$a['hts'].
			'</span><br><a class="link" href="/questions/'.$a['_id'].'/'.$a['url'].'">'.$a['title'].'</a><br>
			You cannot post the same exact question twice';

			throw new QuestionParserException($err);
		}
	}


	/**
	 * Index question
	 *
	 * @todo do this via runLater
	 *
	 * @return object $this
	 */
	protected function addToSearchIndex(){

		IndexerFactory::factory($this->oRegistry)->indexQuestion($this->oQuestion);

		return $this;
	}


	/**
	 * Update QUESTION_TAGS tags counter
	 *
	 * @return object $this
	 */
	protected function addTags(){

		$o = Qtagscounter::factory($this->oRegistry);
		$oQuestion = $this->oQuestion;
		if(count($oQuestion['a_tags']) > 0){
			$callable = function() use($o, $oQuestion){
				$o->parse($oQuestion);
			};
			d('cp');
			runLater($callable);
		}

		return $this;
	}


	/**
	 * Calculates related tags
	 * via shutdown function
	 *
	 * @return object $this
	 */
	protected function addRelatedTags(){

		$oRelated = Relatedtags::factory($this->oRegistry);
		$oQuestion = $this->oQuestion;
		if(count($oQuestion['a_tags']) > 0){
			d('cp');
			$callable = function() use ($oRelated, $oQuestion){
				$oRelated->addTags($oQuestion);
			};
			runLater($callable);
		}
		d('cp');

		return $this;
	}


	/**
	 * Skip if $this->oQuestion['status'] is accptd
	 * which would be the case when question came from API
	 * and is already answered
	 *
	 * @return object $this
	 */
	protected function addUnansweredTags(){
		if('accptd' !== $this->oQuestion['status']){
			if(count($this->oQuestion['a_tags']) > 0){
				$o = new UnansweredTags($this->oRegistry);
				$oQuestion = $this->oQuestion;
				$callable = function() use ($o, $oQuestion){
					$o->set($oQuestion);
				};
				d('cp');
				runLater($callable);
			}
			d('cp');
		}

		return $this;
	}


	/**
	 * Update USER_TAGS collection
	 *
	 * @return object $this
	 */
	protected function addUserTags(){

		$oUserTags = UserTags::factory($this->oRegistry);
		$uid = $this->oSubmitted->getUserObject()->getUid();
		$oQuestion = $this->oQuestion;
		if(count($oQuestion['a_tags']) > 0){
			$callable = function() use ($oUserTags, $uid, $oQuestion){
				$oUserTags->addTags($uid, $oQuestion);
			};
			
			runLater($callable);
		}

		return $this;
	}

}
