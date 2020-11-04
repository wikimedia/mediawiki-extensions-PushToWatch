<?php

class PushToWatch {

	/**
	 * @param Title $title
	 * @param User $user
	 */
	private static function addtoWatch( $title, $user ) {
		global $wgNoReplyAddress, $wgUser;

		$user = User::newFromName( $user );
			if ( !is_object( $user ) || $user->getID() == 0 ) {
				throw new Exception( "Invalid user lookup" );
			}

		if ( $user->isWatched( $title ) ) {
			return;
		}

		$res = $user->addWatch( $title );

		$medit = false;

		$to = new MailAddress( $user->getEmail(), $user->getName(), $user->getRealName() );
		$from = new MailAddress( $wgUser->getEmail(), $wgUser->getName(), $wgUser->getRealName() );
		$replyto = new MailAddress( $wgNoReplyAddress );

		$pagename = $title->getPrefixedText();
		$pageurl = $title->getFullUrl();

		$username = $user->getRealName();
		$wgUsername = $wgUser->getRealName();

		$body = "Hi $username,\r\n$wgUsername requested you to watch $pageurl\r\nCongrats !";
		$subject = "Watchlist injection - $title";

		UserMailer::send( [ $to, $from ], $from, $subject, $body, [ 'replyTo' => $replyto ] );
	}

	/**
	 * @param Title $title
	 * @return string
	 */
	private static function getUsers( $title ) {
		try {
			$dbr = wfGetDB( DB_REPLICA );

			$where = [
				'wl_title' => $title->getDBkey(),
			];

			$join = [
				'user',
				'watchlist'
			];

			$join_conds = [
				'watchlist' => [ 'JOIN', 'user.user_id = watchlist.wl_user' ],
			];

			$res = $dbr->select( $join, 'DISTINCT user_real_name', $where, null, [], $join_conds );

			$output = "No follower";

			if ( $res->numRows() ) {

			$users = [];
			foreach ( $res as $row ) {
				$users[] = $row->user_real_name;

			$output = 'Followers : ' . implode( ', ', $users ) . '.';
			}

			$output .= "<form method='POST'>Push to watch : <input type='submit' style='display:none'/><input type='text' name='pushtowatch_user'/></form>";

			}
		}
		catch ( Exception $e ) {
			error_log( 'Wiki, follower error :' . $e->getMessage() );

			return $output;
		}
	}

	/**
	 * @param SkinTemplate $sk
	 * @param QuickTemplate &$tpl
	 * @return bool
	 */
	public static function listUsers( $sk, &$tpl ) {
		$title = $sk->getRelevantTitle();
		$output = "<hr/>";

		try {
			$user = preg_replace( "#[^a-z]#i", "", $_POST['pushtowatch_user'] );
		if ( $user ) {
			self::addtoWatch( $title, $user );
		}
		} catch ( Exception $e ) {
			$output .= "<div class='error'>Could not add <b>$user</b> to watchlist</div>";
		}

		$output .= self::getUsers( $title );

		$tpl->set( 'followerList',  $output );
		$tpl->data['footerlinks']['info'][] = 'followerList';
		return true;
	}
}
