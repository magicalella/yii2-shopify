<?php
namespace magicalella\shopify;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\base\Exception;

/**
 * Class Shopify
 * Shopify component
 * @package magicalella\shopify
 *
 * @author Raffaella Lollini
 */
class Shopify extends Component
{

    /**
     * @var string
     */
    public $accessToken;
    
    /**
     * @var string
     */
    public $storeName = false;
    public $apiVersion = false;
    
    
    const STATUS_SUCCESS = true;
    const STATUS_ERROR = false;

    /**
     * @throws InvalidConfigException
     */
    public function init()
    {
        if (!extension_loaded('curl')) {
            throw new PrestashopException(
                'Please activate the PHP extension \'curl\' to allow use of PrestaShop webservice library'
            );
    }
        parent::init();
    }

    /**
     * Call Shopify function
     * @param string $call Name of API function to call
     * @param array $data
     * @return response []
     *      status 0/1 success o error
     *      data body della risposta formato object
     *      error eventuali errori di chiamata 
     *      code codice della risposta es 200 o 404
     *      header in header 
     *              X-RateLimit-Limit: 100
     *              X-RateLimit-Remaining: 98
     *              X-RateLimit-Reset: 1580919168
     *              da considerare per gestire il limite delle chiamate
     */
    public function execute($query,$variables = [])
    {
        $url = $this->getUrl();
        $headers = $this->getHeaders();
            
        
        $content = [
            'query' => $query,
        ];
        if($variables)
            $content['variables'] = $variables;
        
        $content = json_encode($content);
        array_push($headers, 'Content-Length: ' . strlen($content));
        
        $response = $this->curl($url,$headers,$content);
        
        $response['data'] = json_decode($response['body']);
        $response['header'] = $this->HeaderToArray($response['header']);
        print_r($response);
        exit();
        return $response;
    }

    /**
     * Do request by CURL
     * @param $url ex: https://api.connectif.cloud/purchases/
     * @param $data
     * @param $method
     * @return response []
     *      status 0/1 success o error
     *      data dati della risposta formato json
     *      error eventuali errori di chiamata 
     *      code codice della risposta es 200 o 404
     *      header in header 
     * X-RateLimit-Limit: 100
     * X-RateLimit-Remaining: 98
     * X-RateLimit-Reset: 1580919168
     * da considerare per gestire il limite delle chiamate
     */
    private function curl($url, $headers,$content)
    {
        $response = [];
        $status = self::STATUS_SUCCESS;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $data = curl_exec($ch);
        $curl_info = curl_getinfo($ch);
        $error = curl_error($ch);
        if($error){
            $status = self::STATUS_ERROR;
        }

        $header_size = $curl_info['header_size'];
        $header = substr($data, 0, $header_size);
        $body = substr($data, $header_size);
        
        $response['status'] = $status;
        $response['body'] = $body;//dati
        $response['error'] = $error;//eventuali errori
        $response['code'] = $curl_info['http_code'];//codice restituito
        $response['header'] = $header;//header 
        
        curl_close($ch);
        
        return $response;
    }
    
    /**
     * @param $target The key in defaultTarget or Url
     * @return string Valid url for start executing
     * @throws InvalidConfigException
     */
    private function getUrl() {
        //https://{storeName}.myshopify.com/admin/api/2025-04/graphql.json
        $url_shopify = '';
        if($this->storeName && $this->apiVersion)
            $url_shopify = 'https://'.$this->storeName.'.myshopify.com/admin/api/'.$this->apiVersion.'/graphql.json';
            
        if (filter_var($url_shopify, FILTER_VALIDATE_URL))
           return $url_shopify;
    
        throw new InvalidConfigException('ShopifyQuery::defaultTarget or $target parameter for GraphqlQuery::execute must be set.');
    }
    
    /**
     * @param $target
     * @return array List of headers, which probably is customized per [[target]]
     */
    private function getHeaders()
    {
        if($this->accessToken){
            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Shopify-Access-Token' => $this->accessToken
            ];
            
            return array_map(function($k, $v){
                return "$k: $v";
            }, array_keys($headers), array_values($headers));
        }
        throw new InvalidConfigException('Shopify::accessToken for ShopifyQuery::execute must be set.');
    }
    
    /**
    * Inutile tanto nel data non esiste code
     * Take the status code and throw an exception if the server didn't return 200 or 201 code
     * <p>Unique parameter must take : <br><br>
     * 'status_code' => Status code of an HTTP return<br>
     * 'response' => CURL response
     {
         status: 400,
         title: 'Bad Request',
         detail: 'Product 5cf8c05f5b4cb901091f7d98, 5cf8c05f5b4cb901091f7d99, 5cf8c05f5b4cb901091f7d9a had URL from non authorized domain.',
         code: 'E0103',
         productsId: [ '5cf8c05f5b4cb901091f7d98', '5cf8c05f5b4cb901091f7d99', '5cf8c05f5b4cb901091f7d9a' ],
         allowedDomains: [ 'http://www.mysite.com' ]
     }
     * </p>
     *
     * @param array $request Response elements of CURL request
     *
     * @throws ConnectifeException if HTTP status code is not 200 or 201
     */
    protected function checkStatusCode($response)
    {
        $error_message = '';
        $title = '';
        $detail = '';
        
        if(key_exists('code', $response)){
            if($response['code'] == '404'){
                $title = $array_response['title'];
                $detail = $array_response['detail'];
            }
        }
        $array_response = json_decode($response);
        switch ($array_response['code']) {
            case '200':
            case '201':
                break;
            case 'E0101':
            case 'E0102':
            case 'E0103':
            case 'E0201':
            case 'E0401':
                $error_message = 'Codice: '.$array_response['code'].' '.$title.' '.$detail;
            break;
            default:
                throw new ConnectifeException(
                    'This call to Shopify Web Services returned an unexpected HTTP status of:' . $array_response['status']
                );
        }
    
        if ($error_message != '') {
            $error_label = 'This call to Shopify failed and returned an HTTP status of %d. That means: %s.';
            throw new ConnectifeException(sprintf($error_label, $request['status_code'], $error_message));
        }
    }

    /**
    *trasforma stringa HEADER del CURL in [] mappo solo i campi che mi servono :
    *              X-RateLimit-Limit: 100
    *              X-RateLimit-Remaining: 98
    *              X-RateLimit-Reset: 1580919168
    * 
    * @parmas stringa header 
    * return []
     */
    protected function HeaderToArray($header){
        $return = [];
        $array_header = explode("\n",$header);
        if(!empty($array_header)){
            foreach($array_header as $val){
                $array_val = explode(':',$val);

                if(!empty($array_val)){
                    switch($array_val[0]){
                        case 'X-RateLimit-Limit':
                        case 'X-RateLimit-Remaining':
                        case 'X-RateLimit-Reset':
                            $chiave = str_replace(['X','-'], '', $array_val[0]);
                            $return[$chiave] = $array_val[1];
                        break;
}
                }
            }
        }
        return $return;
    }


}

/**
 * @package BridgePS
 */
class ConnectifeException extends Exception
{
}
