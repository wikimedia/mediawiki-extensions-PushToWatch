<?php

use MediaWiki\MediaWikiServices;

class PushToWatch {

	/**
	 * Send an email to the supplied user that a page was pushed to their
	 * watchlist.
	 *
	 * @param Title $title Title object representing the page that was pushed to
	 *  the target's watchlist
	 * @param User $user The User to whose watchlist a page was pushed
	 */
	private static function addToWatch( $title, User $user ) {
		global $wgNoReplyAddress;

		if ( !is_object( $user ) || $user->getId() == 0 ) {
			throw new Exception( 'Invalid user lookup' );
		}

		$watchlistManager = MediaWikiServices::getInstance()->getWatchlistManager();

		if ( $watchlistManager->isWatched( $user, $title ) ) {
			return;
		}

		$watchlistManager->addWatch( $user,  $title );

		if ( !$user->isEmailConfirmed() ) {
			return;
		}

		$contextUser = RequestContext::getMain()->getUser();
		$to = new MailAddress( $user->getEmail(), $user->getName(), $user->getRealName() );
		$from = new MailAddress( $contextUser->getEmail(), $contextUser->getName(), $contextUser->getRealName() );
		$replyTo = new MailAddress( $wgNoReplyAddress );

		$pageURL = $title->getFullURL();

		$username = $user->getRealName();
		$currentUser = $contextUser->getRealName();

		$body = wfMessage( 'pushtowatch-email-body', $username, $currentUser, $pageURL )->escaped();
		$subject = wfMessage( 'pushtowatch-email-subject', $title )->escaped();

		UserMailer::send( [ $to, $from ], $from, $subject, $body, [ 'replyTo' => $replyTo ] );
	}

	/**
	 * @param Title $title
	 * @return string
	 */
	private static function getUsers( $title ) {
		$output = '';

		try {
			$dbr = wfGetDB( DB_REPLICA );

			$where = [
				'wl_title' => $title->getDBkey(),
			];

			$tables = [
				'user',
				'watchlist'
			];

			$join_conds = [
				'watchlist' => [ 'JOIN', 'user_id = wl_user' ],
			];

			$res = $dbr->select( $tables, 'DISTINCT user_name', $where, __METHOD__, [], $join_conds );

			$output = wfMessage( 'pushtowatch-no-followers' )->escaped();

			if ( $res->numRows() ) {
				$users = [];

				foreach ( $res as $row ) {
					$users[] = $row->user_name;
				}

				if ( !empty( $users ) ) {
					// @todo FIXME: Use Language#commaList, probably
					// @todo FIXME: Also would be nice to have the user names be links that
					// point to the users' User: pages...
					$output = wfMessage( 'pushtowatch-followers', implode( ', ', $users ) )->escaped();
				}

				$output .= Html::rawElement( 'form', [ 'method' => 'post' ],
					wfMessage( 'pushtowatch' )->escaped() .
					wfMessage( 'word-separator' )->escaped() .
					Html::hidden( 'wpPushToWatchToken', RequestContext::getMain()->getUser()->getEditToken() ) .
					Html::submitButton( '', [ 'style' => 'display:none' ] ) .
					// @todo FIXME: give this element class="mw-autocomplete-user" and add the
					// 'mediawiki.userSuggest' ResourceLoader module to output to enable
					// autocompletion...but it's kinda heavy to add that module to all page loads
					Html::input( 'pushtowatch_user' )
				);
			}
		} catch ( Exception $e ) {
			wfDebugLog( 'PushToWatch', 'Wiki, follower error: ' . $e->getMessage() );
		}

		return $output;
	}

	/**
	 * @param Skin $sk
	 * @param string $key The current key for the current group (row) of footer links. Currently either info or places
	 * @param array &$footerLinks The array of links that can be changed.
	 *    Keys will be used for generating the ID of the footer item; values should be HTML strings.
	 */
	public static function onSkinAddFooterLinks( Skin $sk, string $key, array &$footerLinks ) {
		if ( $key !== 'info' ) {
			return;
		}

		$request = $sk->getRequest();
		// Don't render the form for non-view actions
		if ( $request->getVal( 'action' ) !== 'view' ) {
			return;
		}

		$performingUser = $sk->getUser();
		if ( !$performingUser->isAllowed( 'pushtowatch' ) ) {
			return;
		}

		$title = $sk->getRelevantTitle();
		$output = '<hr />';
		$isTokenOK = $performingUser->matchEditToken( $request->getVal( 'wpPushToWatchToken' ) );

		try {
			if ( $request->wasPosted() && $isTokenOK ) {
				$user = User::newFromName( $sk->getRequest()->getText( 'pushtowatch_user' ) );
				if ( $user ) {
					self::addToWatch( $title, $user );
				}
			} elseif ( $request->wasPosted() && !$isTokenOK ) {
				// CSRF attempt or something...
				$output .= Html::errorBox( $sk->msg( 'sessionfailure' )->parse() );
			}
		} catch ( Exception $e ) {
			$output .= Html::errorBox( $sk->msg( 'pushtowatch-error', $user )->parse() );
		}

		$output .= self::getUsers( $title );

		$footerLinks['followerList'] = $output;
	}

}
