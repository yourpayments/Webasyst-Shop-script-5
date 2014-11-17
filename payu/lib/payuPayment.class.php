<?php

include  dirname(__FILE__)."/payu.cls.php"; 

class payuPayment extends waPayment implements waIPayment
{
    public function allowedCurrency()
    {

        return $this->price_currency ? $this->price_currency : 'RUB';
    }
	
    public function payment($payment_form_data, $order_data, $auto_submit = false)
    {
		$order = waOrder::factory($order_data);
				
		$option = array(
			'merchant' => $this->merchant,
            'secretkey' => $this->secretkey,
            'debug' => $this->debug_mode,
			'luUrl'=>$this->lu_url
        );
		
		$orderID = $order->id;
		$description = preg_replace('/[^\.\?,\[]\(\):;"@\\%\s\w\d]+/', ' ', $order->description);
        $description = preg_replace('/[\s]{2,}/', ' ', $description);
		
		$forSend = array (
          'ORDER_REF' => $orderID, 
          'ORDER_DATE' => date("Y-m-d H:i:s"),
        );
		
		$forSend['ORDER_PNAME'][] = $description;
	    $forSend['ORDER_PINFO'][] = '';
		$forSend['ORDER_PCODE'][] = $order->id;
		$forSend['ORDER_PRICE'][] = number_format($order->total, 2, '.', '');
		$forSend['ORDER_QTY'][] = 1;
		$forSend['ORDER_VAT'][] = $this->VAT;
		$forSend['ORDER_SHIPPING'][] = $order->shipping;
		$forSend['PRICES_CURRENCY'][] = $this->price_currency;
		$forSend['LANGUAGE'][] = $this->language;
		
		if (!empty($order_data['customer_id'])) {
            $contact = new waContact($order_data['customer_id']);
			$forSend['BILL_FNAME'] = $contact->get('firstname');
			$forSend['BILL_LNAME'] = $contact->get('lastname');
			$forSend['BILL_EMAIL'] = $contact->get('email')[0]['value'];
			$forSend['BILL_PHONE'] = $contact->get('phone')[0]['value'];
			$forSend['BILL_ADDRESS'] = $order->billing_address['address'];
			$forSend['BILL_CITY'] = $order->billing_address['city'];
		}
		
		if($this->auto_mode == 1) $forSend['AUTOMODE'] = 1;
		
		$backref = $this->back_ref;
		if($backref != "") $forSend['BACK_REF'] = $backref;
				
		$form = PayU::getInst()->setOptions($option)->setData($forSend)->LU();

        $view = wa()->getView();
        $view->assign('form', $form);

        return $view->fetch($this->path.'/templates/payment.html');
    }

    public function callbackHandler($request)
    {
		$transaction_data = $this->formalizeData($request);
		
		$option = array(
			'merchant' => $this->merchant,
            'secretkey' => $this->secretkey,
        );

		$payansewer = PayU::getInst()->setOptions( $option )->IPN();
		
		$callback_method = self::CALLBACK_PAYMENT;
		$transaction_data = $this->saveTransaction($transaction_data, $request);
        $this->execAppCallback($callback_method, $transaction_data);
		
		echo $payansewer;
		
		return array();
    }
	
	protected function formalizeData($transaction_raw_data)
    {
		$transaction_data = parent::formalizeData($transaction_raw_data);
		
		$ord = $_POST['REFNOEXT'];
	    $ordArray = explode("_", $ord);
		
		$transaction_data['native_id'] = $ORDER_ID;
        $transaction_data['order_id'] = $ORDER_ID;
        $transaction_data['amount'] = $_POST['IPN_TOTALGENERAL'];
		$transaction_data['currency_id'] = $this->price_currency;
		
		return $transaction_data;
	}
}
