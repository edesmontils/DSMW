<?php

/**
 * DSMW Special page
 *
 * @author  Hantz Marlene - jean-Philippe Muller
 */
require_once "$IP/includes/SpecialPage.php";
require_once "$wgP2PExtensionIP/files/utils.php";

/* Extension variables */
$wgExtensionFunctions[] = "wfSetupAdminPage";

class ArticleAdminPage extends SpecialPage {
// Constructor
    function ArticleAdminPage() {
        global $wgHooks, $wgSpecialPages, $wgWatchingMessages;
        # Add all our needed hooks
        $wgHooks["UnknownAction"][] = $this;
        $wgHooks["SkinTemplateTabs"][] = $this;
        SpecialPage::SpecialPage('ArticleAdminPage'/*, "block"*/);// avec block => pasges speciales restreintes
    }

    function getDescription() {
        return "Article Administration Page";
    }

    /**
     * Executed the user opens the DSMW administration special page
     * Calculates the PushFeed list and the pullfeed list (and everything that
     * is displayed on the psecial page
     *
     * @global <Object> $wgOut Output page instance
     * @global <String> $wgServerName
     * @global <String> $wgScriptPath
     * @return <bool>
     */
    function execute() {
        global $wgOut, $wgServerName, $wgScriptPath;/*, $wgSitename, $wgCachePages, $wgUser, $wgTitle, $wgDenyAccessMessage, $wgAllowAnonUsers, $wgRequest, $wgMessageCache, $wgWatchingMessages, $wgDBtype, $namespace_titles;*/

        $url = 'http://'.$wgServerName.$wgScriptPath."/index.php";
        $urlServer = 'http://'.$wgServerName.$wgScriptPath;
        //$wgOut->addHeadItem('script', ArticleAdminPage::javascript());


        $script1 = '<SCRIPT type="text/javascript"> function pushFeedDel(){
  for (var i=0; i < document.getElementsByName("push[]").length; i++)
     {
     if (document.getElementsByName("push[]")[i].checked)
        {
            //alert(document.getElementsByName("push[]")[i].value);
            var feed = document.getElementsByName("push[]")[i].value;
            window.location.href = "'.$url.'/Special:ArticleAdminPage?FeedDel=true&feed="+feed;
        }
     }
}

function pullFeedDel(){
  for (var i=0; i < document.getElementsByName("pull[]").length; i++)
     {
     if (document.getElementsByName("pull[]")[i].checked)
        {
           // alert(document.getElementsByName("pull[]")[i].value);
            var feed = document.getElementsByName("pull[]")[i].value;
            window.location.href = "'.$url.'/Special:ArticleAdminPage?FeedDel=true&feed="+feed;
        }
     }
}

</SCRIPT>';
        $wgOut->addScript($script1);

        ///Special:ArticleAdminPage?FeedDel=true&feed=document.getElementsByName("push[]")[i].value
        if(isset($_GET['FeedDel'])) $this->deleteFeed($_GET['feed']);

        $wgOut->setPagetitle("P2P Administration");


        //Set the limit of rows returned
        $page_limit = 30;
        $i = 0;
        $db   = &wfGetDB(DB_SLAVE);

        $style = ' style="border-bottom: 2px solid #000;"';
        $tableStyle = ' style=" clear:both; float: left; margin-left: 40px; margin-top: 20px"';
        $output = "";



        /////////////PULLFEEDS TABLE//////////////////////////
        $i=0;
        $req = "[[PullFeed:+]][[deleted::false]]";


        $pullFeeds = $this->getRequestedPages($req);


        $output .= '
<FORM METHOD="POST" ACTION="'./*dirname($_SERVER['HTTP_REFERER'])*/$url.'" name="formPull">
<table'.$tableStyle.' >
  <tr>
    <th colspan="5"'.$style.'>PULL:
  <a href='./*dirname($_SERVER['HTTP_REFERER'])*/$url.'?title=administration_pull_site_addition&action=addpullpage>[Add]</a>';

        if ($pullFeeds!=false) {
            $output .='<a href="javascript:pullFeedDel();">[Remove]</a>
  <button type="submit">[Pull]</button></th>
  </tr>
  <tr>
    <th colspan="2" >Site</th>
    <th >Pages</th>
    <th>Remote <br>Patchs</th>
    <th >Local <br>Patchs</th>


  </tr>
  ';
            foreach ($pullFeeds as $pullFeed) {
                $i = $i + 1;
                $pullFeed = str_replace(' ', '_', $pullFeed);
                $tabPage = utils::getPageConcernedByPull($pullFeed);

                $pageConcerned = count($tabPage);

                $pushServer = getPushURL($pullFeed);
                $pushName = getPushName($pullFeed);
                $pushName = str_replace(' ', '_', $pushName);

                $url = $pushServer.'/api.php?action=query&meta=patchPushed&pppushName='.
                    $pushName.'&format=xml';
                $patchXML = file_get_contents($pushServer.'/api.php?action=query&meta=patchPushed&pppushName='.
                    $pushName.'&format=xml');
                $dom = new DOMDocument();
                $dom->loadXML($patchXML);
                $patchPublished = $dom->getElementsByTagName('patch');
                $published = null;
                foreach($patchPublished as $p) {
                    $published[] = $p->firstChild->nodeValue;
                }

                $countRemotePatch = count($published);

                $pulledCS = utils::getSemanticRequest($urlServer,'[[ChangeSet:+]][[inPullFeed::'.$pullFeed.']]','?hasPatch');
                $countPulledPatch = 0;
                foreach ($pulledCS as $CS) {
                    $res = explode('!', $CS);
                    $res = explode(',',$res[1]);
                    $countPulledPatch += count($res);
                }

                $data = //$this->getAwarenessData($row["site_url"]);
                    $output .= '
  <tr>
    <td align="center"><input type="checkbox" id="'.$i.'" name="pull[]" value="'.$pullFeed.'"  /></td>
    <td >'.$pullFeed.'</td>
    <td align="center">['.$pageConcerned.']</td>
    <td align="center">['. $countRemotePatch.']</td>
    <td align="center">['.$countPulledPatch.']</td>
  </tr>';
            }
        }


        $output .= '

<input type="hidden" name="action" value="onpull">
</table>
</FORM>';



        /////////////PUSHFEEDS TABLE//////////////////////////

        $i=0;
        $req = "[[PushFeed:+]][[deleted::false]]";

        $pushFeeds = $this->getRequestedPages($req);

        $output .= '
<FORM METHOD="POST" ACTION="'./*dirname($_SERVER['HTTP_REFERER'])*/$url.'" name="formPush">
<table'.$tableStyle.' >
  <tr>
    <th colspan="5"'.$style.'>PUSH:
  <a href='./*dirname($_SERVER['HTTP_REFERER'])*/$url.'?title=administration_push_site_addition&action=addpushpage>[Add]</a>';
        if ($pushFeeds!=false) {


            $output .= ' <a href="javascript:pushFeedDel();">[Remove]</a>
  <button type="submit">[Push]</button></th>
  </tr>
  <tr>
    <th colspan="2" >Site</th>
    <th >Pages</th>
    <th>Published <br>Patchs</th>
    <th >Unpublished <br>Patchs</th>


  </tr>
  ';
            foreach ($pushFeeds as $pushFeed) {
                $i = $i + 1;

                $pushName = str_replace(' ', '_', $pushName);

                $request = getPushFeedRequest($pushName);
                $tabPage = utils::getSemanticRequest($urlServer,$request,'');
                $countConcernedPage = count($tabPage);

                $url = $urlServer.'/api.php?action=query&meta=patchPushed&pppushName='.
                    $pushName.'&pppageName='.$title.'&format=xml';
                $patchXML = file_get_contents($urlServer.'/api.php?action=query&meta=patchPushed&pppushName='.
                    $pushName.'&pppageName='.$title.'&format=xml');

                $pushName = str_replace(' ', '_', $pushName);
                $output .= '<tr><td><a href="'.$_SERVER['PHP_SELF'].'?title='.$pushName.'">'.$pushName.'</a> : </td>';
                $dom = new DOMDocument();
                $dom->loadXML($patchXML);
                $patchPublished = $dom->getElementsByTagName('patch');
                $published = null;
                foreach($patchPublished as $p) {
                    $published[] = $p->firstChild->nodeValue;
                }
                $countPatchs = count($patchs);
                $countUnpublished = 0;
                //$publishedInPush = utils::getSemanticRequest('http://'.$wgServerName.$wgScriptPath, '', $param);
                if(!is_null($published)) {
                    $unpublished = array_diff($patchs, $published);
                    $countUnpublished = count($unpublished);
                }

                //$this->getAwarenessData($row["site_url"]);
                $output .= '
  <tr>
    <td align="center"><input type="checkbox" id="'.$i.'" name="push[]" value="'.$pushFeed.'" /></td>
    <td >'.$pushFeed.'</td>
    <td align="center">['.$countConcernedPage.']</td>
    <td align="center">[]</td>
    <td align="center">[]</td>
  </tr>';
            }
        }
        $output .= '

<input type="hidden" name="action" value="onpush">
</table>
</FORM>';

        if (!$this->getArticle('Property:ChangeSetID')->exists()) {
            $output .='
<FORM METHOD="POST" ACTION="'.$urlServer.'/extensions/p2pExtension/bot/DSMWBot.php" name="scriptExec">
<table'.$tableStyle.'><td><button type="submit"><b>[UPDATE PROPERTY TYPE]</b></button>
</td></table>
<input type="hidden" name="server" value="'.$urlServer.'">
</form>';
        }

        $wgOut->addHTML($output);
        return false;
    }

    /**
     * $action=admin is generated when the administration tab is clicked
     * Calculates every that is displayed on this page (cf user manual)
     *
     * @global <Object> $wgOut output page instance
     * @global <Object> $wgCachePages
     * @global <String> $wgServerName
     * @global <String> $wgScriptPath
     * @param <String> $action
     * @param <Object> $article
     * @return <bool>
     */
    function onUnknownAction($action, $article) {
        global $wgOut, $wgCachePages, $wgServerName, $wgScriptPath;

        $urlServer = 'http://'.$wgServerName.$wgScriptPath;

        $wgCachePages = false;
        //Verify that the action coming in is "admin"
        if($action == "admin") {
            wfDebugLog('p2p', 'Admin page');
            $title = $article->mTitle->getText();
            wfDebugLog('p2p', ' -> title : '.$title);
            $wgOut->setPagetitle('DSMW on '.$title);

            //part list of patch
            $patchs = utils::orderPatchByPrevious($title);

            $output = '<div style="width:60%;height:40%;overflow:auto;">
<table style="border-bottom: 2px solid #000;">
<caption><b>List of patchs</b></caption>';
            foreach ($patchs as $patch) {
                wfDebugLog('p2p','  -> patchId : '.$patch);
                if(!utils::isRemote($patch)) {
                    wfDebugLog('p2p','      -> remote patch');
                    $output .= '<tr BGCOLOR="#CCCCCC"><td>'.wfTimestamp(TS_RFC2822, $article->getTimestamp()).' : </td>';
                // $output .= '<tr><td BGCOLOR="#33CC00"><a href="'.$_SERVER['PHP_SELF'].'?title='.$patch.'">'.$patch.'</a></td>';
                }else {
                    wfDebugLog('p2p','      -> local patch');
                    $output .= '<tr><td>'.wfTimestamp(TS_RFC2822, $article->getTimestamp()).' : </td>';
                //$output .= '<tr><td><a href="'.$_SERVER['PHP_SELF'].'?title='.$patch.'">'.$patch.'</a></td>';

                }

                $op = utils::getSemanticRequest($urlServer,'[[Patch:+]][[patchID::'.$patch.']]','?hasOperation');
                $countOp = utils::countOperation($op);
                $output .= '<td>'.$countOp['insert'].'  insert, '.$countOp['delete'].' delete</td>';
                $output .= '<td>(<a href="'.$_SERVER['PHP_SELF'].'?title='.$patch.'">'.$patch.'</a>)</td></tr>';
                /*$titlePatch = Title::newFromText( $patch,PATCH );
                $article = new Article( $title );*/
            }
            $output .= '</table></div>';

            //part list of push
            $pushs = utils::getSemanticRequest($urlServer,'[[ChangeSet:+]][[hasPatch::'.$patchs[0].']][[inPushFeed::+]]','?inPushFeed');

            if(!empty ($pushs)){

            $output .= '<br><div><table style="border-bottom: 2px solid #000;"><caption><b>List of pushs</b></caption>';
            foreach ($pushs as $push) {
                $pushName = explode('!',$push);
                $pushName = $pushName[1];

                $output .= '<tr><td><a href="'.$_SERVER['PHP_SELF'].'?title='.$pushName.'">'.$pushName.'</a> : </td>';
               /* $pushName = str_replace(' ', '_', $pushName);
                $url = $urlServer.'/api.php?action=query&meta=patchPushed&pppushName='.
                    $pushName.'&pppageName='.$title.'&format=xml';
                $patchXML = file_get_contents($urlServer.'/api.php?action=query&meta=patchPushed&pppushName='.
                    $pushName.'&pppageName='.$title.'&format=xml');

                $pushName = str_replace(' ', '_', $pushName);

                $dom = new DOMDocument();
                $dom->loadXML($patchXML);
                $patchPublished = $dom->getElementsByTagName('patch');
                $published = null;
                foreach($patchPublished as $p) {
                    $published[] = $p->firstChild->nodeValue;
                }

                //$publishedInPush = utils::getSemanticRequest('http://'.$wgServerName.$wgScriptPath, '', $param);
                if(!is_null($published)) {
                    $unpublished = array_diff($patchs, $published);
                    $output .= '<td>'.count($unpublished).'/'.count($patchs).' unpublished patchs </td></tr>';
                }else {
                    $output .= '<td> '.count($patchs).'/'.count($patchs).' unpublished patchs </td></tr>';
                }*/
            }
            $output .= '</table></div>';

            }//end if empty $pushs

            //part list of pull
            $pulls = utils::getSemanticRequest($urlServer,'[[ChangeSet:+]][[hasPatch::'.$patchs[0].']][[inPullFeed::+]]','?inPullFeed');

            if(!empty ($pulls)){

            $output .= '<div><table width=100><caption><b>List of pull</b></caption>';
            foreach ($pulls as $pull) {
                $pullName = explode('!',$pull);
                $pullName = $pullName[1];
                $pushServer = getPushURL($pullName);
                $pushName = getPushName($pullName);
               /* $pushName = str_replace(' ', '_', $pushName);
                $url = $pushServer.'/api.php?action=query&meta=patchPushed&pppushName='.
                    $pushName.'&pppageName='.$title.'&format=xml';
                $patchXML = file_get_contents($pushServer.'/api.php?action=query&meta=patchPushed&pppushName='.
                    $pushName.'&pppageName='.$title.'&format=xml');

                $output .= '<tr><td><a href="'.$_SERVER['PHP_SELF'].'?title='.$pullName.'">'.$pullName.'</a> : </td>';
                $dom = new DOMDocument();
                $dom->loadXML($patchXML);
                $patchPublished = $dom->getElementsByTagName('patch');
                $published = null;
                foreach($patchPublished as $p) {
                    $published[] = $p->firstChild->nodeValue;
                }

                //$publishedInPush = utils::getSemanticRequest('http://'.$wgServerName.$wgScriptPath, '', $param);
                if(!is_null($published)) {
                    $unpublished = array_diff($patchs, $published);
                    $t = count($published);
                    $t = count($patchs);
                    $count = count($published) - count($patchs);
                    $output .= '<td> '.count($patchs).' patchs and '.$count.' unpulled patchs </td></tr>';
                }else {
                    $output .= '<td> up to date </td></tr>';
                }*/
            }
            $output .= '</table></div>';

            }//end if empty $pulls

            //part push page
            $url = "http://".$wgServerName.$wgScriptPath."/index.php";
            $output .= '
<div><FORM METHOD="POST" ACTION='.$url.' name="formPush">
<table >
  <tr><td> <button type="submit">[Push page : "'.$title.'"]</button></td></tr>
<input type="hidden" name="action" value="onpush"/>
<input type="hidden" name="push" value="PushFeed:PushPage_'.$title.'"/>
<input type="hidden" name="request" value="[['.$title.']]"/>
<input type="hidden" name="page" value="'.$title.'"/></table></form></div>';


            $wgOut->addHTML($output);
            return false;
        }
         else {
            return true;
        }
    }
    /**
     * Defines the "Article Admin tab"
     *
     * @global <type> $wgRequest
     * @global <type> $wgServerName
     * @global <type> $wgScriptPath
     * @param <type> $skin
     * @param <type> $content_actions
     * @return <type>
     */
    function onSkinTemplateTabs(&$skin, &$content_actions) {
        global $wgRequest, $wgServerName, $wgScriptPath;
        $urlServer = 'http://'.$wgServerName.$wgScriptPath;

        $action = $wgRequest->getText("action");
        $db = &wfGetDB(DB_SLAVE);

        $patchCount = 0;
        $patchList = utils::getSemanticRequest($urlServer,'[[Patch:+]][[onPage::'.$skin->mTitle->getText().']]','?patchID');
        $patchCount = count($patchList);
        if($skin->mTitle->mNamespace == PATCH
            || $skin->mTitle->mNamespace == PULLFEED
            || $skin->mTitle->mNamespace == PUSHFEED
            || $skin->mTitle->mNamespace == CHANGESET
        ) {
        }else {

            $content_actions["admin"] = array(
                "class" => ($action == "admin") ? "selected" : false,
                "text" => "Article admin (".$patchCount." patches)",
                "href" => $skin->mTitle->getLocalURL("action=admin")
            );
        }
        return false;
    }


    /**
     *replaces the deleted semantic attribute in the feed page (pullfeed:.... or
     * pushfeed:....)
     * This aims to "virtualy" delete the article, it will no longer appear in the
     * special page (Special:ArticleAdminPage)
     *
     * @param <String> $feed
     * @return <boolean>
     */
    function deleteFeed($feed) {
    //if the browser page is refreshed, feed keeps the same value
    //but [[deleted::false| ]] isn't found and nothing is done
        preg_match( "/^(.+?)_*:_*(.*)$/S", $feed, $m );
        $articleName = $m[2];
        if($m[1]=="PullFeed") $title = Title::newFromText($articleName, PULLFEED);
        elseif($m[1]=="PushFeed") $title = Title::newFromText($articleName, PUSHFEED);
        else throw new MWException( __METHOD__.': no valid namespace detected' );
        //get PushFeed by name

        $dbr = wfGetDB( DB_SLAVE );
        $revision = Revision::loadFromTitle($dbr, $title);
        $pageContent = $revision->getText();

        $dbr = wfGetDB( DB_SLAVE );
        $revision = Revision::loadFromTitle($dbr, $title);
        $pageContent = $revision->getText();

        //update deleted Value
        $result = str_replace("[[deleted::false| ]]", "[[deleted::true| ]]", $pageContent);
        if($result=="") return true;
        $pageContent = $result;

        //save update
        $article = new Article($title);
        $article->doEdit($pageContent, $summary="");

        return true;
    }

    /**
     * @param <String> $title
     * @return <String>
     */
    function getPageIdWithTitle($title) {
        $dbr = wfGetDB( DB_SLAVE );
        $id = $dbr->selectField('page','page_id', array(
            'page_title'=>$title));
        return $id;
    }

    /**
     * returns an array of page titles received via the request
     */
    function getRequestedPages($request) {
        global $wgServerName, $wgScriptPath;
        $req = utils::encodeRequest($request);
        $url1 = 'http://'.$wgServerName.$wgScriptPath."/index.php/Special:Ask/".$req."/headers=hide/format=csv/sep=,/limit=100";
        $string = file_get_contents($url1);
        $res = explode("\n", $string);
        foreach ($res as $key=>$page) {
            if($page=="") {
                unset ($res[$key]);
            }else {
            //$page = strtr($page, "\"", "\0");
            //            $pos = strrpos($page, ":");//NS removing
            //            if ($pos === false) {
            //                // not found...
            //            }else{
            //                $page = substr($page, $pos+1);
            //            }
                $res[$key] = str_replace("\"", "", $page);
            //$res[$key] = strtr($page, "\"", "");
            }
        }

        return $res;
    }

    function getArticle( $article_title ) {
        $title = Title::newFromText( $article_title );

        // Can't load page if title is invalid.
        if ($title == null)     return null;
        $article = new Article($title);

        return $article;
    }


} //end class

/* Global function */
# Called from $wgExtensionFunctions array when initialising extensions
function wfSetupAdminPage() {
    global $wgUser;
    SpecialPage::addPage( new ArticleAdminPage );
    if ($wgUser->isAllowed("ArticleAdminPage")) {
        global $wgArticleAdminPage;
        $wgArticleAdminPage = new ArticleAdminPage();
    }
}


 ?>
