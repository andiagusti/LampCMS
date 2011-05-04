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
 *    the website\'s Questions/Answers functionality is powered by lampcms.com
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


namespace Lampcms\Modules\Tumblr;

/**
 * Class repsesents on blog post to be
 * sent to Tumblr Blog
 *
 * @author Dmitri Snytkine
 *
 */
class TumblrPost extends TumblrContent
{
	/**
	 * Title of blog post
	 *
	 * @var string
	 */
	protected $title = '';


	/**
	 * Body of blog post
	 * may contain html
	 *
	 * @var string
	 */
	protected $body = '';


	/**
	 * string to make SEO-friendly url
	 *
	 * @var string
	 */
	protected $slug = '';


	/**
	 * Comma-separated tags
	 *
	 * @var string
	 */
	protected $tags = '';


	/**
	 * (non-PHPdoc)
	 * @see Lampcms\Modules\Tumblr.TumblrContent::getType()
	 */
	public function getType(){
		return 'regular';
	}


	/**
	 * Setter for $this->title
	 *
	 * @param string $s title of blog post
	 * @throws \InvalidArgumentException if $s not a string
	 * @return object $this
	 */
	public function setTitle($s){
		if(!is_string($s)){
			throw new \InvalidArgumentException('$s must be a string. Was: '.gettype($s));
		}

		$this->title = $s;

		return $this;
	}


	/**
	 * Getter for $this->title
	 * @return string
	 */
	public function getTitle(){
		return $this->title;
	}


	/**
	 * Getter for $this->body
	 * @return string of blog post body (may be html)
	 */
	public function getBody(){
		return $this->body;
	}


	/**
	 * Getter for this->tags
	 *
	 * @return string comma separated list of tags
	 * or empty string if there are no tags
	 */
	public function getTags(){
		return $this->tags;
	}


	/**
	 * Getter for $this->slug
	 *
	 * @return string
	 */
	public function getSlug(){
		return $this->slug;
	}


	/**
	 * Setter for $this->body
	 *
	 * @param string $s
	 * @throws \InvalidArgumentException if input not a string
	 *
	 * @return object $this
	 */
	public function setBody($s){
		if(!is_string($s)){
			throw new \InvalidArgumentException('$s must be a string. Was: '.gettype($s));
		}

		$this->body = $s;

		return $this;
	}


	/**
	 * Setter for $this->tags
	 *
	 * @param array $a array of tags
	 *
	 * @return object $this
	 */
	public function setTags(array $a){

		$this->tags = implode(',', $a);
		d('$this->tags: '.$this->tags);
		
		return $this;
	}


	/**
	 * Setter for $this->slug
	 *
	 * @param string $s
	 * @throws \InvalidArgumentException if input not a string
	 *
	 * @return object $this
	 */
	public function setSlug($s = ''){
		if(!is_string($s)){
			throw new \InvalidArgumentException('$s must be a string. Was: '.gettype($s));
		}

		$this->slug = $s;

		return $this;
	}
}