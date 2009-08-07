<?php
if( !defined('MEDIAWIKI') ) {
// Eclipse helper - will be ignored in production
    require_once( 'ApiQueryBase.php' );
}

/**
 * Description of ApiQueryPatchPush
 *
 *
 * @author hantz
 */
class ApiPatchPush extends ApiQueryBase {
    public function __construct( $query, $moduleName ) {
        parent :: __construct( $query, $moduleName, 'pp' );
    }
    public function execute() {
        $this->run();
    }

    public function encodeRequest($request) {
        $req = str_replace(
            array('-', '#', "\n", ' ', '/', '[', ']', '<', '>', '&lt;', '&gt;', '&amp;', '\'\'', '|', '&', '%', '?', '{', '}'),
            array('-2D', '-23', '-0A', '-20', '-2F', '-5B', '-5D', '-3C', '-3E', '-3C', '-3E', '-26', '-27-27', '-7C', '-26', '-25', '-3F', '-7B', '-7D'), $request);
        return $req;
    }
    private function run() {
        global $wgServerName, $wgScriptPath;

        $params = $this->extractRequestParams();
        wfDebugLog('p2p','ApiQueryPatchPushed params '.$params['pushName']);

        $publishedInPush = getPublishedPatches($params['pushName']);
        $published = null;

        //filtered on published patch on page title
        if(isset ($params['pageName'])) {
            foreach ($publishedInPush as $patch) {
                if(count(utils::getSemanticRequest('http://'.$wgServerName.$wgScriptPath,'[[Patch:+]][[patchID::'.$patch.']][[onPage::'.$params['pageName'].']]',''))) {
                    $published[] = $patch;
                }
            }
        }else{
            $published = $publishedInPush;
        }
        $result = $this->getResult();
        if(!is_null($published)) {
            $result->setIndexedTagName($published,'patch');
            $result->addValue('query', $this->getModuleName(), $published);
            $result->addValue(array('query',$this->getModuleName()),'pushFeed',$params['pushName']);
        }
    }

    public function getAllowedParams() {
        global $wgRestrictionTypes, $wgRestrictionLevels;

        return array (
        'pushName' => array (
        ApiBase :: PARAM_TYPE => 'string',
        ),
        'pageName' => array (
        ApiBase :: PARAM_TYPE => 'string',
        ),

        );
    }

    public function getParamDescription() {
        return array(
        'pushName' => 'patch published in pushName',
        'pageName' => 'patch of this pageName',
        );
    }

    public function getDescription() {
        return 'Return information of patches.';
    }

    protected function getExamples() {
        return array(
        'api.php?action=query&meta=patchPushed&pppushName=PushToto&pppageName=Titi&format=xml',
        );
    }

    public function getVersion() {
        return __CLASS__ . ': $Id: ApiQueryPatch.php xxxxx 2009-07-24 09:00:00Z hantz $';
    }
}
?>
