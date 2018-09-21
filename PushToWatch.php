<?php
/**
 * PushToWatch extension for MediaWiki
 * Allows to push a page to another users watchlist
 *
 * @link https://www.mediawiki.org/wiki/Extension:PushToWatch Documentation
 *
 * @file
 * @ingroup Extensions
 * @package MediaWiki
 * @author cloudyks
 * @copyright (C) 2013 cloudyks
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

if ( !defined( 'MEDIAWIKI' ) ) {
        die( 'This file is an extension to MediaWiki and thus not a valid entry point.' );
}

$wgExtensionCredits['other'][] = array(
        'name' => 'PushToWatch',
        'version' => '0.2.0',
        'author' => 'cloudyks',
        'descriptionmsg' => 'pushtowatch-desc',
        'url' => 'https://www.mediawiki.org/wiki/Extension:PushToWatch',
);

$wgMessagesDirs['PushToWatch'] = __DIR__ . '/i18n';

$wgHooks['SkinTemplateOutputPageBeforeExec'][] = 'PushToWatch::ListUsers';

Class PushToWatch {

  private static function addtoWatch($title, $user){
    global $wgNoReplyAddress, $wgUser;

    $user = User::newFromName($user);
    if(!is_object( $user) || $user->getID() == 0)
        throw new Exception("Invalid user lookup");

    if($user->isWatched($title))
        return;


    $res = $user->addWatch($title);

    $medit = false;

    $to      = new MailAddress( $user->getEmail(), $user->getName(), $user->getRealName() );
    $from    = new MailAddress( $wgUser->getEmail(), $wgUser->getName(), $wgUser->getRealName() );
    $replyto = new MailAddress( $wgNoReplyAddress );

    $pagename = $title->getPrefixedText();
    $pageurl  = $title->getFullUrl();

    $username = $user->getRealName();
    $wgUsername = $wgUser->getRealName();

    $body = "Hi $username,\r\n$wgUsername requested you to watch $pageurl\r\nCongrats !";
    $subject = "Watchlist injection - $title";

    UserMailer::send( array($to, $from), $from, $subject, $body, array( 'replyTo' => $replyto ) );
  }

  private static function getUsers($title){

    try {
      $dbr = wfGetDB( DB_SLAVE );

      $where = array(
        'wl_title' => $title->getUserCaseDBKey(),
      );

      $join = array(
        'user',
        'watchlist'
      );

      $join_conds = array(
        'watchlist' => array('JOIN', 'user.user_id = watchlist.wl_user'),
      );

      $res = $dbr->select($join, 'DISTINCT user_real_name', $where, null, [], $join_conds);

      $output = "No follower";

      if($res->numRows()){

        $users = array();
        foreach ($res as $row) {
          $users[] = $row->user_real_name;
        }

        $output = 'Followers : '.join(', ', $users).'.';
      }

      $output .= "<form method='POST'>Push to watch : <input type='submit' style='display:none'/><input type='text' name='pushtowatch_user'/></form>";

      return $output;
    }
    catch(Exception $e){
      error_log('Wiki, follower error :'.$e->getMessage());
    }
  }

  public static function ListUsers( $sk, &$tpl ) {
    $title = $sk->getRelevantTitle();
    $output = "<hr/>";

    try {
      $user = preg_replace("#[^a-z]#i", "", $_POST['pushtowatch_user']);
      if($user) {
        self::addtoWatch($title, $user);
      }
    } catch(Exception $e){
        $output .= "<div class='error'>Could not add <b>$user</b> to watchlist</div>";
    }

    $output .= self::getUsers($title);


    $tpl->set( 'followerList',  $output);
    $tpl->data['footerlinks']['info'][] = 'followerList';
    return true;
  }
}
