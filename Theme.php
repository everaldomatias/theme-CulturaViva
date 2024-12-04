<?php

namespace CulturaViva;

use MapasCulturais\API;
use MapasCulturais\ApiQuery;
use MapasCulturais\App;
use MapasCulturais\Entities\Agent;
use MapasCulturais\Entities\AgentMeta;
use MapasCulturais\Entities\Opportunity;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

        $app->hook('ApiQuery(Agent).params', function (&$api_params) use($seals, $theme, $app) {
            /** @var ApiQuery $this */
            
            if($app->config['rcv.disableApiFilters']) {
                return;
            }
            if (!isset($api_params['type']) && !isset($api_params['id']) && !isset($api_params['parent']) && !isset($api_params['owner']) && !isset($api_params['user'])) {
                $api_params['type'] = API::EQ(2);
            }
        });


        $app->hook('ApiQuery(Agent).joins', function (&$joins) use($seals, $theme, $app) {
            /** @var ApiQuery $this */

            if($app->config['rcv.disableApiFilters']) {
                return;
            }
            $joins .= "
                LEFT JOIN e.__metadata rcv_tipo 
                    WITH rcv_tipo.key = 'rcv_tipo'";
            
            if(!$theme->canUserControlRCV()) {
                $joins .= "
                    LEFT JOIN e.__sealRelations rcv_sealRelations
                    LEFT JOIN rcv_sealRelations.seal rcv_seal WITH rcv_seal.id IN ($seals)";
            }

            if($app->auth->isUserAuthenticated() && !$app->user->is('admin')) {
                $joins .= " LEFT JOIN e.__permissionsCache rcv_pcache_agent WITH rcv_pcache_agent.action = '@control'";
            }
        });

        $app->hook('ApiQuery(Agent).where', function (&$where) use($theme, $app) {
            /** @var ApiQuery $this */

            if($app->config['rcv.disableApiFilters']) {
                return;
            }

            $_where = "((e._type = 2 AND rcv_tipo.value = 'ponto') OR e._type = 1)";

            if(!$theme->canUserControlRCV()) {
                $_where .= " AND ((e._type = 2 AND rcv_seal.id IS NOT NULL) OR e._type = 1)";
            }

            if($app->auth->isUserAuthenticated()) {
                if($app->user->is('admin')) {
                    $_where = "e.user = {$app->user->id} OR ($_where)";
                } else {
                    $_where = "rcv_pcache_agent.user = {$app->user->id} OR ($_where)";
                }
            }

            $where .= " AND ({$_where})";
        });

        // versão anterior do filtro de espaço feito com subquery
        // $app->hook('ApiQuery(Space).params', function (&$api_params) use($seals, $theme, $app) {
        //     /** @var ApiQuery $this */
        //     if($app->config['rcv.disableApiFilters']) {
        //         return;
        //     }
        //     $agent_query = new ApiQuery(AgentMeta::class, ['@select' => 'value', 'key' => API::EQ('rcv_sede_spaceId')]);
        //     $this->addFilterByApiQuery($agent_query, 'value', 'id');
        // });

        $app->hook('ApiQuery(Space).joins', function (&$joins) use($seals, $theme, $app) {
            if($app->config['rcv.disableApiFilters']) {
                return;
            }
            
            $agent_meta_class = AgentMeta::class;
            $joins .= "
                JOIN e.owner rcv_space_owner
                JOIN $agent_meta_class rcv_space_owner_meta
                    WITH rcv_space_owner_meta.key = 'rcv_sede_spaceId'
                JOIN rcv_space_owner_meta.owner rcv_space_owner_meta_agent 
                    WITH rcv_space_owner_meta_agent.user = rcv_space_owner.user";

            
            if($app->auth->isUserAuthenticated() && !$app->user->is('admin')) {
                $joins .= " 
                    LEFT JOIN e.__permissionsCache rcv_pcache_space 
                        WITH rcv_pcache_space.action = '@control'";
            }
        });

        $app->hook('ApiQuery(Space).where', function (&$where) use($theme, $app) {
            /** @var ApiQuery $this */

            if($app->config['rcv.disableApiFilters']) {
                return;
            }

            $_where = "CAST(rcv_space_owner_meta.value AS INTEGER) = e.id";

            if($app->auth->isUserAuthenticated()) {
                if($app->user->is('admin')) {
                    $_where = "rcv_space_owner.user = {$app->user->id} OR ($_where)";
                } else {
                    $_where = "rcv_pcache_space.user = {$app->user->id} OR ($_where)";
                }
            }

            $where .= " AND ({$_where})";
        });
    }

    function canUserControlRCV() {
        return $this->opportunity->canUser('@control');
    }
}
