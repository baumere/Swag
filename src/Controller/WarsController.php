<?php
namespace App\Controller;

use Cake\Event\Event;
use Cake\I18n\Time;
use Cake\Core\Configure;

class WarsController extends AppController
{
    public function initialize()
    {
        parent::initialize();
    }

    public function beforeFilter(Event $event)
    {
        parent::beforeFilter($event);
        $this->viewBuilder()->layout('tables');
    }

    public function view($id = 0)
    {
        $this->loadModel('Matches');
        $contain = ['Guild', 'GuildOpp', 'Fights'];
        $query = $this->Matches->find('all', [
            'contain' => $contain,
            'conditions' => ['Matches.match_id' => $id]
        ]);

        $matches = $query->toArray();

        $timezoneToRegion = Configure::read('TimezoneToRegion');
        $regionToTimezone = array_flip($timezoneToRegion);
        $timezone = $regionToTimezone[$matches[0]['guild']['region_id']];
        $matches[0]['last_fight'] = Time::parse($matches[0]['last_fight'])->i18nFormat('yyyy-MM-dd HH:mm:ss', $timezone);
        // get attacker and defender name
        if($matches[0]['log_type'] === 1) {
            $attacker = $matches[0]['guild']['name'];
            $defender = $matches[0]['guild_opp']['name'];
        } else {
            $attacker = $matches[0]['guild_opp']['name'];
            $defender = $matches[0]['guild']['name'];
        }

        $this->set(compact('matches', 'attacker', 'defender'));
    }

    public function week($id = 0)
    {
        $this->loadModel('Matches');

        $year = ($this->request->query('year')) ? $this->request->query('year') : date('Y');
        $week = $this->request->query('week') ? $this->request->query('week') : date('W');

        $dto = new Time();
        $this->battle_start = $dto->setISODate($year, $week)->modify('-1 days')->format('Y-m-d');
        $this->battle_end = $dto->modify('+7 days')->format('Y-m-d');

        $contain = ['Guild', 'GuildOpp', 'Fights' => function ($q) {
            return $q->where(['Fights.battle_end >' => $this->battle_start, 'Fights.battle_end <' => $this->battle_end]);
        }];
        $query = $this->Matches->find('all', [
            'contain' => $contain,
            'conditions' => ['Matches.last_fight >' => $this->battle_start, 'Matches.last_fight <' => $this->battle_end],
            'order' => ['Matches.match_id' => 'DESC']
        ])->where(['Matches.guild_id' => $id])->orWhere(['Matches.opp_guild_id' => $id, 'Matches.last_fight >' => $this->battle_start, 'Matches.last_fight <' => $this->battle_end]);
        $matches = $query->toArray();

        $timezoneToRegion = Configure::read('TimezoneToRegion');
        $regionToTimezone = array_flip($timezoneToRegion);
        $timezone = $regionToTimezone[$matches[0]['guild']['region_id']];

        foreach ($matches as &$match) {
            $match['last_fight'] = Time::parse($match['last_fight'])->i18nFormat('yyyy-MM-dd HH:mm:ss', $timezone);
        }

        $guildname = $matches[0]['guild_id'] == $id ? $matches[0]['guild']['name'] : $matches[0]['guild_opp']['name'];
        $this->set(compact('matches', 'guildname'));
    }
}
?>