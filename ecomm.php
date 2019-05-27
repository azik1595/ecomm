<!DOCTYPE html>
<html>
<title>ECOMM</title>
</head>

<?php
/**
 * Класс для работы с платежной системой  ECOMM
 */
class Payment
{
	const CURRENCY_RUB = 840;
	protected $merchantID = 0;
	protected $keyDir = 'C:\xampp\htdocs\ecomm\keys';
	protected $paymentUrl = 'https://ecomm.yourbank.uz:9443/ecomm/MerchantHandler';
	protected $redirectUrl = 'https://ecomm.yourbank.uz:8443/ecomm/ClientHandler';
	protected $logFile = 'log.txt';
	/**
	 * Инициирует класс настройками подключения к платежному шлюзу
	 * @param $merchantID int Merchant ID клиента
	 * @param $keyDir string Директория, где располагаются файлы .key, .pem, .crt
	 * @param null $paymentUrl string Адрес ресурса для осуществления платежей
	 * @param null $redirectUrl string Адрес ресурса для перенаправления на страницу оплаты
	 */
	public function __construct($merchantID , $keyDir  , $paymentUrl = null, $redirectUrl = null)
	{
		
		if (empty($merchantID) || !is_numeric($merchantID)) {
			throw new WrongParamException('Wrong merchantID parameter');
		}
		
		if (!is_dir($keyDir)) {
			throw new WrongParamException('Key directory do not exist');
		}
		$this->$merchantID = $merchantID;
		$this->keyDir = $keyDir;
		
		if ($paymentUrl) {
			$this->paymentUrl = $paymentUrl;
		}
		
		if ($redirectUrl) {
			$this->redirectUrl = $redirectUrl;
		}
	}
	/**
	 * Регистрирует транзакцию в платежной системе
	 * @param $amount int Сумма транзакции в целых единицах
	 * @param int $currency Код валюты (ISO 4217). По умолчанию – рубль
	 * @param string $description Детали транзакции. Количество символов – 125 латиницей
	 * @return bool|string Идентификатор транзакции в случае успеха или false в случае ошибки
	 */
	public function createTransaction($amount, $currency = self::CURRENCY_RUB, $description = '')
	{
		$params = array(
			'client_ip_addr' => $this->getClientIP(),
			'command' => 'v',
			'amount' => $amount * 100,
			'description' => $description,
			'currency' => $currency,
			'language' => 'uz',
		);
		$result = $this->sendRequest($params);
		$this->log(__METHOD__ . ' - ' . print_r($params, true) . print_r($result, true));
		if (!empty($result['TRANSACTION_ID'])) {
			return $result['TRANSACTION_ID'];
		}
		return false;
	}
	/**
	 * Возвращает статус транзакции по ее идентификатору
	 * Формат ответа:
	 * <pre>
	 * RESULT: <result>
	 * RESULT_PS: <result_ps>
	 * RESULT_CODE: <result_code>
	 * 3DSECURE: <3dsecure>
	 * RRN: <rrn>
	 * APPROVAL_CODE: <app_code>
	 * CARD_NUMBER: <pan
	 * </pre>
	 * @param $transID string Идентификатор транзакции
	 * @return array|bool Массив с полями статуса или false в случае ошибки
	 */
	public function getStatus($transID)
	{
		$params = array(
			'client_ip_addr' => $this->getClientIP(),
			'command' => 'c',
			'trans_id' => $transID,
		);
		$result = $this->sendRequest($params);
		$this->log(__METHOD__ . ' - ' . print_r($params, true) . print_r($result, true));
		return $result;
	}
	/**
	 * Завершает бизнес-день и возвращает данные
	 * Формат ответа:
	 * <pre>
	 * RESULT: <result> OK | FAILED
	 * RESULT_CODE: <result_code>
	 * FLD_075: <fld_075>
	 * FLD_076: <fld_076>
	 * FLD_087: <fld_087>
	 * FLD_088: <fld_088>
	 * </pre>
	 * @return bool|mixed Массив с отчет по итогам дня, либо false в случае ошибки
	 */
	public function closeDay()
	{
		$params = array(
			// 'server_version' => '2.0',
			'command' => 'b',
		);
		$result = $this->sendRequest($params);
		$this->log(__METHOD__ . ' - ' . print_r($params, true) . print_r($result, true));
		return $result;
	}
	/**
	 * Отменяет транзацию по ее идентификатору
	 * @param $transID string Идентификатор транзации
	 * @param null $amount int Сумма отмены. По умолчанию полная сумма.
	 * Не учитывается, если указан параметр $suspectedFraud
	 * @param $suspectedFraud bool Флаг, указывающий, что откат делается из-за подозрения в мошенничестве
	 * @return bool|mixed
	 */
	public function reverseTransaction($transID, $amount = null, $suspectedFraud = false)
	{
		$params = array(
		
			'command' => 'r',
			'trans_id' => $transID,
		);
		if ($suspectedFraud) {
			$params['suspected_fraud'] = 1;
		} else if ($amount !== null) {
			$params['amount'] = $amount;
		}
		$result = $this->sendRequest($params);
		$this->log(__METHOD__ . ' - ' . print_r($params, true) . print_r($result, true));
		return $result;
	}
	/**
	 * Возвращает ссылку для перенаправления на страницу оплаты
	 * @param $transID string Идентификатор транзации
	 * @return string
	 */
	public function getRedirectUrl($transID)
	{
		return urlencode($this->redirectUrl . $transID);
	}
	/**
	 * Устанавливает файл, в который будет осуществляться логирование
	 * @param $path string
	 */
	public function setLogFile($path)
	{
		$this->logFile = $path;
	}
	/**
	 * Выполняет запрос к платежному серверу и возвращает ответ в виде массива параметр => значение
	 * @param array $data Параметры HTTP запроса
	 * @return bool|mixed Возвращает false в случае ошибки, массив параметров ответа в случае успеха
	 */
	protected function sendRequest($data = array())
	{
		$result = array();
		
		$curl = curl_init();
		$options = array(
			CURLOPT_USERAGENT => __CLASS__ . ' HTTP client',
			CURLOPT_URL => $this->paymentUrl,
			CURLOPT_HEADER => false,
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSLKEYPASSWD =>'yourpass',
			CURLOPT_SSLCERT => 'C:\xampp\htdocs\ecomm\keys\imakstore.pem',
		    // CURLOPT_CAINFO =>'C:\xampp\htdocs\ecomm\keys\imakstore.pem',
			CURLOPT_POSTFIELDS => http_build_query($data),
		);
		curl_setopt_array($curl, $options);
		$response = curl_exec($curl);
		print_r($response);
		if ($response === false) {
			$this->log(__METHOD__ . ' curl error: ' . print_r(curl_error($curl), true));
			curl_close($curl);
			return false;
		}
		curl_close($curl);
		if (substr($response, 0, 6) === 'error:') {
			$this->log(__METHOD__ . ' response error: ' . substr($response, 6));
			return false;
		}
		if (preg_match_all('#(.*)\:(.*)(?:\n|$)#Uis', $response, $matches)) {
			foreach ($matches[1] as $key => $val) {
				$result[$val] = $matches[2][$key];
			}
			return $result;
		}
		return false;
	}
	public function getHtml($trans_id)
	{
		$result = array();
		$params_tran = array(
			'trans_id' => $trans_id,
			'language' =>'uz'
		);
		$curl = curl_init();
		$options = array(
			CURLOPT_USERAGENT => __CLASS__ . ' HTTP client',
			CURLOPT_URL => 'https://ecomm.yourbank.uz:9443/ecomm/ClientHandler',
			CURLOPT_HEADER => false,
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSLKEYPASSWD =>'yourpass',
			CURLOPT_SSLCERT => 'C:\xampp\htdocs\ecomm\keys\imakstore.pem',
		    // CURLOPT_CAINFO =>'C:\xampp\htdocs\ecomm\keys\imakstore.pem',
			CURLOPT_POSTFIELDS => http_build_query($params_tran),
		);
		curl_setopt_array($curl, $options);
		$response = curl_exec($curl);
		return $response;
		
		
	}
	/**
	 * Возвращает клиентский IP адрес текущего запроса
	 * @return string
	 */
	public function getClientIP()
	{
		return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
	}
	/**
	 * Сохраняет историю запросов в файл {@link Payment::$logFile}
	 * Можно переопределить в дочерних классах для использования собственного логирования
	 * @param $message string Строка сообщения
	 */
	public function log($message)
	{
		$message = date('Y-m-d H:i:s') . ' ' . __CLASS__ . ' ' . $message . PHP_EOL;
		if ($this->logFile) {
			file_put_contents($this->logFile, $message, FILE_APPEND);
		}
	}
}


class WrongParamException extends Exception {};
$payment = new Payment(12,'C:\xampp\htdocs\ecomm\keys');
$transID = $payment ->createTransaction(8,860,'abad ');
$strhtml = $payment->getHtml($transID);
$dochtml = new DOMDocument();
$dochtml->loadHTML($strhtml);
$cardentry = $dochtml->getElementById('cardentry');
$cardentry->setAttribute("action", "https://ecomm.yourbank.uz:8443/ecomm/ClientHandler");
echo $dochtml->saveHTML();
// print_r($payment->closeDay());
// Redirect($payment->getRedirectUrl($transIDS) , false); 
// header('Location: https://ecomm.yourbank.uz:9443/ecomm/ClientHandler?trans_id=' . urlencode($transIDS) , true, 301)
?>
 <form action = "https://ecomm.yourbank.uz:6443/ecomm2/ClientHandler" method = "GET">
         trans_id: <input type = "text" name = "trans_id" id='trans_id_form'/>
         <input type = "submit" />
      </form>
</html>
