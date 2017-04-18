<?php
/**
 * Created by PhpStorm.
 * User: plume
 * Date: 2017/4/10
 * Time: 1:22
 */
namespace App\Http\Controllers;

use App\Libraries\TelegramAPI;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    private $update;
    private $chat_id;
    private $tg_user_id;
    private $bot_id;
    private $group_id;
    private $tg_api;

    public function __construct($bot_id, $group_id, $update=false)
    {
        //
        $this->update = $update;
        $this->chat_id = $this->update["message"]["chat"]["id"];
        $this->tg_user_id = $this->update["message"]['from']['id'];
        $this->bot_id = $bot_id;
        $this->group_id = $group_id;
        $this->tg_api = new TelegramAPI($this->bot_id);
    }

    //
    public function start() {
        $now = Date('Y-m-d H:i:s');
        $fan = DB::table('fans')->where('telegram_user_id', $this->tg_user_id)->first();
        if(!$fan && !empty($this->tg_user_id)) {
            DB::table('fans')->insert([
                'username' => $this->update["message"]['from']['first_name'],
                'telegram_user_id' => $this->tg_user_id,
                'chat_id' => $this->chat_id,
                'created_at' => $now,
                'updated_at' => $now
            ]);
        }
        $new_user_msg = ['text'=>$this->update["message"]['from']['first_name']." 加入"];
        $this->tg_api->sendMessage("307558399",$new_user_msg);
        $reply =  ['text'=>"欢迎使用\n/sublist 查看未订阅的成员列表\n/unsublist 查看已订阅的成员列表"];
        return $this->tg_api->sendMessage($this->chat_id, $reply);
    }

    public function subscribeList() {
        $fan = DB::table('fans')->where('telegram_user_id', $this->tg_user_id)->first();
        if(!$fan) {
            return "error";
        }
        $subscribed_member_id_list = DB::table('idol_fans_relation')->where('fan_id', $fan->id)->pluck('member_id');
        $subscribed_member_id_list[] = '-1';
        $other_member_list = DB::table('idol_members')
            ->where('group_id', $this->group_id)
            ->whereNotIn('id', $subscribed_member_id_list)
            ->get();
        if(count($other_member_list)<1) {
            $reply = ['text'=>"你已经关注了全部成员了"];
        } else {
            $inline_keyboard = [];
            $inline_keyboard_one_row = [];
            $i=0;
            foreach ($other_member_list as $member) {
                if($i==3) {
                    $i=0;
                    $inline_keyboard[] = $inline_keyboard_one_row;
                    $inline_keyboard_one_row = [];
                }

                $inline_keyboard_one_row[] = ['text'=>$member->name, 'callback_data'=>'sub@'.$member->id];
                $i++;
            }
            $inline_keyboard[] = $inline_keyboard_one_row;
            $reply = [
                'text' => "以下是你尚未订阅的成员列表，点击即可订阅",
                'reply_markup' => ['inline_keyboard'=>$inline_keyboard]
            ];
        }
        $this->tg_api->sendMessage($this->chat_id, $reply);
        return "success";
    }

    public function unsubscribeList() {
        $fan = DB::table('fans')->where('telegram_user_id', $this->tg_user_id)->first();
        if(!$fan) {
            return "error";
        }
        $subscribed_member_id_list = DB::table('idol_fans_relation')->where('fan_id', $fan->id)->pluck('member_id');
        $subscribed_member_list = DB::table('idol_members')
            ->where('group_id', $this->group_id)
            ->whereIn('id', $subscribed_member_id_list)
            ->get();
        if(count($subscribed_member_list)<1) {
            $reply = ['text'=>"你还没有订阅成员"];
        } else {
            $inline_keyboard = [];
            $inline_keyboard_one_row = [];
            $i=0;
            foreach ($subscribed_member_list as $member) {
                if($i==3) {
                    $i=0;
                    $inline_keyboard[] = $inline_keyboard_one_row;
                    $inline_keyboard_one_row = [];
                }

                $inline_keyboard_one_row[] = ['text'=>$member->name, 'callback_data'=>'unsub@'.$member->id];
                $i++;
            }
            $inline_keyboard[] = $inline_keyboard_one_row;
            $reply = [
                'text' => "以下是已经订阅的成员列表，点击即可退订",
                'reply_markup' => ['inline_keyboard'=>$inline_keyboard]
            ];
        }
        $this->tg_api->sendMessage($this->chat_id, $reply);
        return "success";
    }
}