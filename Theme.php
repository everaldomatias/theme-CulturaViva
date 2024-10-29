<?php

namespace CulturaViva;

use MapasCulturais\API;
use MapasCulturais\App;
use MapasCulturais\Entities\Opportunity;

class Theme extends \MapasCulturais\Themes\BaseV2\Theme
{

    protected Opportunity $opportunity;

    static function getThemeFolder()
    {
        return __DIR__;
    }

    function _init()
    {
        parent::_init();

        $app = App::i();

        $seals = $app->config['rcv.seals'];
        $opportunity_id = $app->config['rcv.opportunityId'];

        $this->opportunity = $app->repo('Opportunity')->find($opportunity_id);

        $theme = $this;

        $app->hook('ApiQuery(Agent).params', function (&$api_params) use($seals, $theme) {
            if (!isset($api_params['type']) && !isset($api_params['id']) && !isset($api_params['parent']) && !isset($api_params['owner']) && !isset($api_params['user'])) {
                $api_params['type'] = API::EQ(2);
            }
        });


        $app->hook('ApiQuery(Agent).joins', function (&$joins) use($seals, $theme, $app) {
            $joins .= "
                LEFT JOIN e.__metadata rcv_tipo 
                    WITH rcv_tipo.key = 'rcv_tipo'";

            if(!$theme->canUserControlRCV()) {
                $joins .= "
                    LEFT JOIN e.__sealRelations rcv_sealRelations
                    LEFT JOIN rcv_sealRelations.seal rcv_seal WITH rcv_seal.id IN ($seals)";
            }

            if($app->auth->isUserAuthenticated() && !$app->user->is('admin')) {
                $joins .= " LEFT JOIN e.__permissionsCache rcv_pcache WITH pcache.action = '@control'";
            }
        });

        $app->hook('ApiQuery(Agent).where', function (&$where) use($theme, $app) {
            $_where = "((e._type = 2 AND rcv_tipo.value = 'ponto') OR e._type = 1)";

            if(!$theme->canUserControlRCV()) {
                $_where .= " AND ((e._type = 2 AND rcv_seal.id IS NOT NULL) OR e._type = 1)";
            }

            if($app->auth->isUserAuthenticated()) {
                if($app->user->is('admin')) {
                    $_where = "e.user = {$app->user->id} OR ($_where)";
                } else {
                    $_where = "rcv_pcache.user = {$app->user->id} OR ($_where)";
                }
            }

            $where .= "AND ($_where)";
        });
    }

    function canUserControlRCV() {
        return $this->opportunity->canUser('@control');
    }
}
