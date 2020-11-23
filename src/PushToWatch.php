<?php

class PushToWatch {

	/**
	 * @param Title $title
	 * @param string $user
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

		$user->addWatch( $title );

		$to = new MailAddress( $user->getEmail(), $user->getName(), $user->getRealName() );
		$from = new MailAddress( $wgUser->getEmail(), $wgUser->getName(), $wgUser->getRealName() );
		$replyto = new MailAddress( $wgNoReplyAddress );

		$pageurl = $title->getFullUrl();

		$username = $user->getRealName();
		$currentUser = $wgUser->getRealName();

		$body = "Hi $username,\r\n$currentUser requested you to watch $pageurl\r\nCongrats !";
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

				$output .= Html::rawElement( 'form', [ 'method' => 'POST' ],
					'Push to watch : ' .
					Html::submitButton( '', [ 'style' => 'display:none' ] ) .
					Html::input( 'pushtowatch_user' )
				);
			}
		} catch ( Exception $e ) {
			error_log( 'Wiki, follower error :' . $e->getMessage() );
		}

		return $output;
	}

	/**
	 * @param SkinTemplate $sk
	 * @param QuickTemplate $tpl
	 */
	public static function listUsers( $sk, $tpl ) {
		$title = $sk->getRelevantTitle();
		$output = "<hr/>";

		try {
			$user = $sk->getRequest()->getText( 'pushtowatch_user' );
			// FIXME: This destroys all usernames that contain other characters!
			$user = preg_replace( "#[^a-z]#i", "", $user );
			if ( $user ) {
				self::addtoWatch( $title, $user );
			}
		} catch ( Exception $e ) {
			$output .= "<div class='error'>Could not add <b>$user</b> to watchlist</div>";
		}

		$output .= self::getUsers( $title );

		$tpl->set( 'followerList',  $output );
		$tpl->data['footerlinks']['info'][] = 'followerList';
	}
}
