<?php

/* *
 * Submit Order
 * */
$app->post('/transac/{user_uuid}', function ($request, $response, $args) {
	
	$data = $request->getParsedBody();
	
	//check for data, return false if empty
	if ( empty($data) ) {
		return $response->withJson(array("status" => false, "message" => 'Empty Form!'), 404);
	}	
	
	//check items
	if (empty($data['items'])) {
		$response->withJson(array("status" => false, "message" => "No Item(s) Found!") , 404);
		return $response;		
	}  

	//check user if valid
	$userId = $this->UserUtil->checkUser($args['user_uuid']);
	if ( ! $userId ) {
		$response->withJson(array("status" => false, "message" =>"Invalid User!"), 404);
		return $response;
	}
	$data['transac']['user_id'] = $userId;

	//check given price
	$blnPrice = $this->TransacUtil->checkPrices($data['items']);
	if(!$blnPrice) {
		$response->withJson(array("status" => false, "message" =>"Price Changes Detected!"), 404);
		return $response;		
	}
	
	//get the sub_total (add all amount in items)
	$fltItemTotal = 0;
	foreach ( $data['items'] as $value ) {
		$fltTotal = $value['qty'] * $value['price'];
		if (!empty($value['discount']))
			$fltTotal = $fltTotal - $value['discount'];

		$fltItemTotal += $fltTotal;
	}	
	$data['transac']['sub_total'] = $fltItemTotal;
	
	//check delivery cost
	if (!empty($data['transac']['address_id'])){
		$blnDeliveryCost = $this->TransacUtil->checkDeliveryCost($data['transac']['address_id'], $data['transac']['delivery_cost']); 
		if (!$blnDeliveryCost)
			return $response->withJson(array("status" => false, "message" =>"Delivery Cost Not Matched!"), 404);
	}
	
	//check promo code if exist or not expired. return the promo discount amount
	if (!empty($data['transac']['promo_code'])) {
		$blnDiscount = $this->TransacUtil->checkPromoCode($data['transac']['promo_code'], $data['transac']['discount']);
		if (!$blnDiscount)
			return $response->withJson(array("status" => false, "message" =>"Discount Not Matched!"), 404);		
	}
	
	//get the total amount
	$data['transac']['total_amount'] = $data['transac']['sub_total'] + $data['transac']['delivery_cost'] - $data['transac']['discount']; 
	
	//set uuid	
	//$data['transac']['uuid'] = uniqid();
	
	try {
		
		$data['transac']['uuid'] = uniqid();
		
		//insert into transactions table
		$arrFields = array_keys($data['transac']);
		$arrValues = array_values($data['transac']);
		$insertStatement = $this->db->insert( $arrFields )
								->into('transactions')
								->values($arrValues);
		$intTransacId = $insertStatement->execute(true);
		$strUuid = $this->db->select(array('uuid'))->from('transactions')->where('id','=',$intTransacId)->execute(false)->fetch();
		
		//insert into transaction_items
		foreach ( $data['items'] as $value ) {
			
			$value['transaction_id'] = $intTransacId; 
			
			//get total amount each item
			$value['total_amount'] = $value['qty'] * $value['price'] - $value['discount'];
			
			$arrFields = array_keys($value);
			$arrValues = array_values($value);

			//insert into items table
			$insertStatement = $this->db->insert( $arrFields )
										->into('transaction_items')
										->values($arrValues);
			$insertId = $insertStatement->execute(true);			
		}
		
		return $response->withJson(array("status" => true, "data" =>$strUuid), 200);
				
	} catch (Exception $e) {

		return $response->withJson(array("status" => false, "message" =>$e->getMessage()), 500);
		
	}

});


/* *
 * Get Single Orders "order"
 * */
$app->get('/transac/order/{user_uuid}/{trasac_uuid}', function($request, $response, $args){

	//check user if valid
	$userId = $this->UserUtil->checkUser($args['user_uuid']);
	if ( ! $userId ) {
		return $response->withJson(array("status" => false, "message" =>"Invalid User!"), 404);
	}

	//check transac if valid
	$arrTransac = $this->TransacUtil->checkTransac($args['trasac_uuid'], $userId);
	if ( ! $arrTransac ) {
		return $response->withJson(array("status" => false, "message" =>"No Record(s) Found!"), 404);
	}
	
	try {
		
		//get items on this transactions
		$selectStmt = $this->db->select()->from('transaction_items')->where('transaction_id','=',$arrTransac['id']);
		$selectStmt = $selectStmt->join('menus', 'transaction_items.menu_id','=','menus.id');
		$selectStmt = $selectStmt->execute();
		$arrResult = $selectStmt->fetchAll();
		
		//get menu images
		if (!empty($arrResult))
			$arrResult = $this->MenuUtil->getMenuImages($arrResult);
		
		$arrTransac['items'] = $arrResult;
		
		return $response->withJson(array("status" => true, "message" =>$arrTransac), 200);		
		
	} catch (Exception $e) {
		
		return $response->withJson(array("status" => false, "message" =>$e->getMessage()), 500);
		
	}

});

/* *
 * Get All Orders of a user - "orders"
* */
$app->get('/transac/orders/{user_uuid}[/{status}]', function($request, $response, $args){

	//check user if valid
	$userId = $this->UserUtil->checkUser($args['user_uuid']);
	if ( ! $userId ) {
		return $response->withJson(array("status" => false, "message" =>"Invalid User!"), 404);
	}
	
	$intStatus = isset($args['status']) ? $args['status'] : null;

	$arrTransac = $this->TransacUtil->checkTransac(null, $userId, $intStatus);
	if ( ! $arrTransac ) {
		return $response->withJson(array("status" => false, "message" =>"No Record(s) Found!"), 404);
	}
	
	try {
		
		//Extract transaction_id
		foreach ($arrTransac as $v) {
			$arrTransacId[] = $v['id'];
		}
		
		//get items on this transactions
		$selectStmt = $this->db->select()->from('transaction_items')->whereIn('transaction_id', $arrTransacId );
		$selectStmt = $selectStmt->join('menus', 'transaction_items.menu_id','=','menus.id');
		$selectStmt = $selectStmt->execute();
		$arrResult = $selectStmt->fetchAll();
		
		//get menu images
		if (!empty($arrResult)) {
			$arrResult = $this->MenuUtil->getMenuImages($arrResult);
		
			//make transaction_id as the key
			$arrResultNew = [];
			foreach ($arrResult as $v)
				$arrResultNew[$v['transaction_id']][] = $v; 	
		
			$arrResult = $arrResultNew;
		}
		
		//set the items of each transactions
		$arrTransacNew = [];
		foreach ($arrTransac as $v) {
			$v['items'] = $arrResult[$v['id']];
			$arrTransacNew[] = $v;
		}
		$arrTransac = $arrTransacNew;

		return $response->withJson(array("status" => true, "data" =>$arrTransac), 200);

	} catch (Exception $e) {

		return $response->withJson(array("status" => false, "message" =>$e->getMessage()), 500);

	}

});


/* *
 * Update transaction_item status 
 * */
$app->put('/transac/order/item/{user_uuid}/{trasac_uuid}', function($request, $response, $args){
	
	$data = $request->getParsedBody();
	
	//check user if valid
	$userId = $this->UserUtil->checkUser($args['user_uuid']);
	if ( ! $userId ) {
		return $response->withJson(array("status" => false, "message" =>"Invalid User"), 404);
	}	
	
	//check transac if valid
	$arrTransac = $this->TransacUtil->checkTransac($args['trasac_uuid'], $userId);
	if ( ! $arrTransac ) {
		return $response->withJson(array("status" => false, "message" =>"No Record(s) Found!"), 404);
	}
	

	try {
			
		$updateStmt = $this->db->update( array('status' => $data['status']) )
								->table('transaction_items')
								->whereIn('id',$data['id'],'AND')
								->where('transaction_id','=',$arrTransac['id']);
		$intCount = $updateStmt->execute();
		
		//if no rows updated
		if ( ! $intCount ) {
			return $response->withJson(array("status" => false, "message" =>"No Record(s) Found!"), 404);
		}
		
		//select the updated items
		$selectStmt = $this->db->select()->from('transaction_items')->whereIn('id',$data['id'],'AND')->where('transaction_id','=',$arrTransac['id']);
		$selectStmt = $selectStmt->execute();
		$arrResult = $selectStmt->fetchAll();		
		
		return $response->withJson(array("status" => true, "data" =>$arrResult), 200);
		
	} catch (Exception $e) {
		
		return $response->withJson(array("status" => false, "message" =>$e->getMessage()), 500);
		
	}
	
});

/* *
 * Update Order Transactions details
 * - address_id
 * - delivery_man_id
 * - status = 1 = waiting-for-payment; 2 = dispatched; 3 = delivered; 4 = completed; 5 = archived;
 * - transac_ref
* */
$app->put('/transac/order/{user_uuid}/{trasac_uuid}', function($request, $response, $args){

	//allowed field to be updated
	$arrAllowedField = array('delivery_man_id','address_id','status','transac_ref');
	
	$data = $request->getParsedBody();
	
	//check user if valid
	$userId = $this->UserUtil->checkUser($args['user_uuid']);
	if ( ! $userId ) {
		return $response->withJson(array("status" => false, "message" =>"Invalid User"), 404);
	}

	//check transac if valid
	$arrTransac = $this->TransacUtil->checkTransac($args['trasac_uuid'], $userId);
	if ( ! $arrTransac ) {
		return $response->withJson(array("status" => false, "message" =>"No Record(s) Found!"), 404);
	}


	try {
		
		//make sure ONLY accept delivery_man_id / status / address_id		
		foreach ($data as $k => $v) {
			if (!in_array($k, $arrAllowedField))
				return $response->withJson(array("status" => false, "message" => "Update Not Allowed! - " . $k), 404);
		} 

		$updateStmt = $this->db->update( $data )
								->table('transactions')
								->where('id','=',$arrTransac['id']);
		$intCount = $updateStmt->execute();
				

		//if no rows updated
		if ( ! $intCount ) {
			return $response->withJson(array("status" => false, "message" =>"No Record(s) Found!"), 404);
		}

		//select the updated items
		$selectStmt = $this->db->select()->from('transactions')->where('id','=',$arrTransac['id']);
		$selectStmt = $selectStmt->execute();
		$arrResult = $selectStmt->fetchAll();

		return $response->withJson(array("status" => true, "data" =>$arrResult), 200);

	} catch (Exception $e) {

		return $response->withJson(array("status" => false, "message" =>$e->getMessage()), 500);

	}

});

//Get promo discount

//Get delivery cost

//Set delivery man

//Set payment method