<?php

namespace App\Http\Controllers;

use App\Telegram;
use Carbon\Carbon;
use coinmarketcap\api\CoinMarketCap;
use Exception; 
use Illuminate\Http\Request;
use Telegram\Bot\Api;

class TelegramController extends Controller
{
    protected $telegram;
    protected $chatId;
    protected $username;
    protected $text;

    public function __construct()
    {
        $this->telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
    }

    public function getMe()
    {
        $response = $this->telegram->getMe();
        return $response;
    }

    public function setWebHook()
    {
        // $url = '<a class="vglnk" href="https://e816-118-97-161-114.ngrok.io/" rel="nofollow"><span>https</span><span>://</span><span>e816-118-97-161-114</span><span>.</span><span>ngrok</span><span>.</span><span>io</span><span>/</span></a>' . env('TELEGRAM_BOT_TOKEN') . '/webhook';
        $url = 'https://e816-118-97-161-114.ngrok.io/' . env('TELEGRAM_BOT_TOKEN') . '/webhook';
        $response = $this->telegram->setWebhook(['url' => $url]);

        return $response == true ? redirect()->back() : dd($response);
    }

    public function handleRequest(Request $request)
    {
        $this->chatId = $request['message']['chat']['id'];
        $this->username = $request['message']['from']['username'];
        $this->text = $request['message']['text'];

        switch ($this->text) {
            case '/start':
            case '/menu':
                $this->showMenu();
                break;
            case '/getGlobal':
                $this->showGlobal();
                break;
            case '/getTicker':
                $this->getTicker();
                break;
            case '/getCurrencyTicker':
                $this->getCurrencyTicker();
                break;
            default:
                $this->checkDatabase();
        }
    }

    public function showMenu($info = null) 
    {
        $message = '';
        if ($info) {
            $message .= $info . chr(10);
        }
        $message .= '/menu' . chr(10);
        $message .= '/getGlobal' . chr(10);
        $message .= '/getTicker' . chr(10);
        $message .= '/getCurrencyTicker' . chr(10);

        $this->sendMessage($message);
    }

    public function showGlobal()
    {
        $data = CoinMarketCap::getGlobalData();

        $this->sendMessage($this->formatArray($data), true);
    }

    public function test()
    {
        $url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest';
        $parameters = [
            'start' => '1',
            'limit' => '5000',
            'convert' => 'USD'
        ];

        $headers = [
            'Accepts: application/json',
            'X-CMC_PRO_API_KEY: 5d52672c-74a5-4cd0-86b0-20df404274cf'
        ];
        $qs = http_build_query($parameters); // query string encode the parameters
        $request = "{$url}?{$qs}"; // create the request URL


        $curl = curl_init(); // Get cURL resource
        // Set cURL options
        curl_setopt_array($curl, array(
            CURLOPT_URL => $request,            // set the request URL
            CURLOPT_HTTPHEADER => $headers,     // set the headers 
            CURLOPT_RETURNTRANSFER => 1         // ask for raw response instead of bool
        ));

        $response = curl_exec($curl); // Send the request, save the response
        echo "<pre>";
        print_r(json_decode($response)); // print json decoded response
        echo "</pre>";
        curl_close($curl);
    }

    public function getTicker()
    {
        $data = CoinMarketCap::getTicker();

        $formattedData = "";

        foreach ($data as $datum) {
            $formattedData .= $this->formatArray($datum);
            $formattedData .= "----------\n";
        }

        $this->sendMessage($formattedData, true);
    }

    public function getCurrencyTicker()
    {
        $message = "Please enter the name of the Cryptocurrency";

        Telegram::create([
            'username' => $this->username,
            'command' => __FUNCTION__
        ]);

        $this->sendMessage($message);
    }

    public function checkDatabase()
    {
        try {
            $telegram = Telegram::where('username', $this->username)->latest()->firstOrFail();
            
            if ($telegram->command == 'getCurrencyTicker') {
                $response = CoinMarketCap::getCurrencyTicker($this->text);

                if (isset($response['error'])) {
                    $message = 'Sorry no such cryptocurrency found';
                } else {
                    $message = $this->formatArray($response[0]);
                }

                Telegram::where('username', $this->username)->delete();

                $this->sendMessage($message, true);
            }
        } catch (Exception $exception) {
            $error = "Sorry, no such cryptocurrency found. \n";
            $error .= "Please select one of the following options";
            $this->showMenu($error);
        }
    }

    protected function formatArray($data)
    {
        $formattedData = "";
        foreach ($data as $item => $value) {
            $item = str_replace("_", " ", $item);
            if ($item == 'last updated') {
                $value = Carbon::createFromTimestampUTC($value)->diffForHumans();
            }
            $formattedData .= "<b>{$item}</b>\n";
            $formattedData .= "\t{$value}\n";
        }

        return $formattedData;
    }

    protected function sendMessage($message, $parseHTML = false)
    {
        $data = [
            'chat_id' => $this->chatId,
            'text' => $message,
        ];

        if ($parseHTML) $data['parse_mode'] = 'HTML';

        $this->telegram->sendMessage($data);
    }
}
