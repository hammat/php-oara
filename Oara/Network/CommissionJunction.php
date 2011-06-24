<?php
/**
 * Export Class
 *
 * @author     Carlos Morillo Merino
 * @category   Oara_Network_Cj
 * @copyright  Fubra Limited
 * @version    Release: 01.00
 *
 */
class Oara_Network_CommissionJunction extends Oara_Network{
    /**
     * Export Merchants Parameters
     * @var array
     */
    private $_exportMerchantParameters = null;
    /**
     * Export Transaction Parameters
     * @var array
     */
    private $_exportTransactionParameters = null;
    
    /**
     * Export Transaction Parameters
     * @var array
     */
    private $_exportOverviewParameters = null;
    
     /**
     * merchantMap.
     * @var array
     */
    private $_merchantMap = array();

    /**
     * Client 
     * @var unknown_type
     */
    private $_client = null;
    
    /**
     * Client 
     * @var unknown_type
     */
    private $_apiClient = null;
    /**
     * Member id 
     * @var int
     */
    private $_memberId = null;
    /**
     * API Password 
     * @var string
     */
    private $_apiPassword = null;
    /**
     * Constructor and Login
     * @param $cj
     * @return Oara_Network_Cj_Export
     */
    public function __construct($credentials)
    {
    	
        $user = $credentials['user'];
        $password = $credentials['password'];
        $this->_apiPassword = $credentials['apiPassword'];
        
        
        
        $loginUrl = 'https://members.cj.com/member/foundation/memberlogin.do?';
        $valuesLogin = array(new Oara_Curl_Parameter('uname', $user),
                             new Oara_Curl_Parameter('pw', $password),
                             new Oara_Curl_Parameter('submit.x', '6'),
                             new Oara_Curl_Parameter('submit.y', '8')
                            );

        $this->_client = new Oara_Curl_Access($loginUrl, $valuesLogin, $credentials);
        
        $this->_exportMerchantParameters = array(new Oara_Curl_Parameter('sortKey', 'active_start_date'),
                                                 new Oara_Curl_Parameter('sortOrder', 'DESC'),
                                                 new Oara_Curl_Parameter('contractView', 'ALL'),
                                                 new Oara_Curl_Parameter('contractView', 'ALL'),
                                                 new Oara_Curl_Parameter('format', '6'),
                                                 new Oara_Curl_Parameter('contractState', 'active'),
                                                 new Oara_Curl_Parameter('column', 'merchantid'),
                                                 new Oara_Curl_Parameter('column', 'websitename'),
                                                 new Oara_Curl_Parameter('column', 'merchantcategory')
                                                 );
        
        $this->_exportTransactionParameters = array(new Oara_Curl_Parameter('actionname', '0'),
                                                    new Oara_Curl_Parameter('period', 'range'),
                                                    new Oara_Curl_Parameter('what', 'commDetail'),
                                                    new Oara_Curl_Parameter('corrected', ''),
                                                    new Oara_Curl_Parameter('dtType', 'event'),
                                                    new Oara_Curl_Parameter('filterby', '-1'),
                                                    new Oara_Curl_Parameter('actiontype', ''),
                                                    new Oara_Curl_Parameter('status', ''),
                                                    new Oara_Curl_Parameter('filter', ''),
                                                    new Oara_Curl_Parameter('website', ''),
                                                    new Oara_Curl_Parameter('preselectrange', 'today'),
                                                    new Oara_Curl_Parameter('download', 'csv')
                                                   );                                     
        $this->_exportOverviewParameters = array(
        										 new Oara_Curl_Parameter('perfPubByWebsite', ''),
        										 new Oara_Curl_Parameter('periodValue', ''),
        										 new Oara_Curl_Parameter('what_name', 'All Web Sites'),
        										 new Oara_Curl_Parameter('what', 'perfPubByAdvCompany'),
        										 new Oara_Curl_Parameter('period', 'range'),
                                                 new Oara_Curl_Parameter('download', 'csv')
                                                 );
        
        $this->_exportPaymentParameters = array(new Oara_Curl_Parameter('startRow', '0'),
                                                 new Oara_Curl_Parameter('sortKey', ''),
                                                 new Oara_Curl_Parameter('sortOrder', ''),
                                                 new Oara_Curl_Parameter('format', '6'),
                                                 new Oara_Curl_Parameter('button', 'Go')
                                                 );
        $urls = array();
        $urls[] = new Oara_Curl_Request('https://members.cj.com/member/publisher/home.do', array());
        $result = $this->_client->get($urls);
        if (preg_match("/a href=\"\/member\/(.*)?\/foundation/", $result[0], $matches)){
            $this->_memberId = trim($matches[1]);
        } else {
        	throw new Exception('No member id found');
        }
    }
	/**
	 * Check the connection
	 */
	public function checkConnection(){
		$connection = true;
		$restUrl = 'https://commission-detail.api.cj.com/v3/commissions?date-type=event';
		$client = new Zend_Http_Client($restUrl);
        $client->setHeaders('Authorization',$this->_apiPassword);
		$response = $client->request('GET');
		if ($response->getStatus() != 200 ){
			$connection = false;
		}
		return $connection;
	}
    
    /**
     * (non-PHPdoc)
     * @see library/Oara/Network/Oara_Network_Interface#getMerchantList()
     */
    public function getMerchantList($merchantMap = array()){
        $merchants = array();
        $merchantsExport = self::getMerchantExport();
        foreach ($merchantsExport as $merchantData) {
            $obj = Array();
            $obj['cid'] = $merchantData[0];
            $obj['name'] = $merchantData[1];
            $merchants[] = $obj;
        }
    	$this->_merchantMap = $merchantMap;
		foreach ($merchants as $merchant){
			if (!isset($this->_merchantMap[$merchant['name']])){
				$this->_merchantMap[$merchant['name']] = $merchant['cid'];
			}
		}
        return $merchants;
    }

    /**
     * (non-PHPdoc)
     * @see library/Oara/Network/Oara_Network_Interface#getTransactionList($idMerchant, $dStartDate, $dEndDate)
     */
    public function getTransactionList($merchantList = null, Zend_Date $dStartDate = null, Zend_Date $dEndDate = null){
        $totalTransactions = Array();
        //The end data for the API has to be one day more 
        $dEndDate->addDay(1);
    	$restUrl = 'https://commission-detail.api.cj.com/v3/commissions?date-type=event&start-date='.$dStartDate->toString("yyyy-MM-dd").'&end-date='.$dEndDate->toString("yyyy-MM-dd");
        //Setting the client.
		$client = new Zend_Http_Client($restUrl);
        $client->setHeaders('Authorization',$this->_apiPassword);
		$response = $client->request('GET');
    	$xml = simplexml_load_string($response->getBody(), null, LIBXML_NOERROR | LIBXML_NOWARNING);
		if(isset($xml->commissions->commission)){
			foreach ($xml->commissions->commission as $singleTransaction){
				
				if (in_array((int)self::findAttribute($singleTransaction, 'cid'),$merchantList)){
	   				
		            $transaction = Array();
		            $transaction['merchantId'] = self::findAttribute($singleTransaction, 'cid');
		            $transactionDate =  new Zend_Date(self::findAttribute($singleTransaction, 'event-date'),'yyyy-MM-ddTHH:mm:ss'); 
		            $transaction['date'] = $transactionDate->toString("yyyy-MM-dd HH:mm:ss");
		           
		            if (self::findAttribute($singleTransaction, 'action-status') == 'closed' || self::findAttribute($singleTransaction, 'action-status') == 'locked'){
		                $transaction['status'] = Oara_Utilities::STATUS_CONFIRMED;
		            } else if (self::findAttribute($singleTransaction, 'action-status') == 'extended' || self::findAttribute($singleTransaction, 'action-status') == 'new'){
		                $transaction['status'] = Oara_Utilities::STATUS_PENDING;
		            } else if (self::findAttribute($singleTransaction, 'action-status') == 'corrected'){
		                $transaction['status'] = Oara_Utilities::STATUS_DECLINED;
		            }
		                        
		            $transaction['amount'] = Oara_Utilities::parseDouble(self::findAttribute($singleTransaction, 'sale-amount'));
		            $transaction['commission'] = Oara_Utilities::parseDouble(self::findAttribute($singleTransaction, 'commission-amount'));
		            $totalTransactions[] = $transaction;
	   			}
				
			}
		}
        
        /**
        
        $dateArray = Oara_Utilities::daysOfDifference($dStartDate, $dEndDate);
        foreach ($dateArray as $currentStartDate){
        	$urls = array();
        	echo $currentStartDate->get(Zend_Date::DAY)."\n\n";
	        $valuesFromExport = Oara_Utilities::cloneArray($this->_exportTransactionParameters);
	        $valuesFromExport[] = new Oara_Curl_Parameter('startyear', $currentStartDate->get(Zend_Date::YEAR));
	        $valuesFromExport[] = new Oara_Curl_Parameter('startmonth', (int)$currentStartDate->get(Zend_Date::MONTH) - 1);
	        $valuesFromExport[] = new Oara_Curl_Parameter('startday', $currentStartDate->get(Zend_Date::DAY));
	        
	        $valuesFromExport[] = new Oara_Curl_Parameter('endyear', $currentStartDate->get(Zend_Date::YEAR));
	        $valuesFromExport[] = new Oara_Curl_Parameter('endmonth', (int)$currentStartDate->get(Zend_Date::MONTH) - 1);
	        $valuesFromExport[] = new Oara_Curl_Parameter('endday', $currentStartDate->get(Zend_Date::DAY));
	        $valuesFromExport[] = new Oara_Curl_Parameter('cid', '');
	        
	        $urls[] = new Oara_Curl_Request('https://members.cj.com/member/'.$this->_memberId.'/publisher/report/transaction.do?', $valuesFromExport);
        	$exportReport = $this->_client->get($urls);
        }
        
        $numberReports = count($exportReport);
        
        for ($j = 0; $j < $numberReports; $j++){
	        $exportData = str_getcsv($exportReport[$j],"\n");
	        $num = count($exportData);
	        for ($i = 1; $i < $num; $i++) {
	            $transactionExportArray = str_getcsv($exportData[$i],",");
	            if (count($transactionExportArray) <= 13){
	            	throw new Exception ('Fail getting the transactions');
	            }
	   			if (in_array((int)$transactionExportArray[13],$merchantList)){
	   				
		            $transaction = Array();
		            $transaction['merchantId'] = $transactionExportArray[13]; 
		            //$transaction['website'] = trim($transactionExportArray[11]); 
		            //$transaction['link'] = self::readLink($transactionExportArray[12]);
		
		            $dateString = substr($transactionExportArray[1], 0, -4);
		            $transactionDate =  new Zend_Date($dateString,'yyyy-MMM-dd HH:mm','en_US');  
		            $transactionDate->setSecond(00);
		               
		            $transaction['date'] = $transactionDate->toString("yyyy-MM-dd HH:mm:ss");
		            $transaction['program'] = $transactionExportArray[2];
		           
		            if ($transactionExportArray[5] == 'closed' || $transactionExportArray[5] == 'locked'){
		                $transaction['status'] = Oara_Utilities::STATUS_CONFIRMED;
		            } else if ($transactionExportArray[5] == 'extended' || $transactionExportArray[5] == 'new'){
		                $transaction['status'] = Oara_Utilities::STATUS_PENDING;
		            } else if ($transactionExportArray[5] == 'corrected'){
		                $transaction['status'] = Oara_Utilities::STATUS_DECLINED;
		            }
		                        
		            $transaction['amount'] = Oara_Utilities::parseDouble($transactionExportArray[7]);
		            $transaction['commission'] = Oara_Utilities::parseDouble($transactionExportArray[9]);
		            $totalTransactions[] = $transaction;
	   			}
	        }
        }
        
        **/
        
        return $totalTransactions;
    }
	/**
	 * (non-PHPdoc)
	 * @see library/Oara/Network/Oara_Network_Base#getOverviewList($merchantId, $dStartDate, $dEndDate)
	 */
    public function getOverviewList($transactionList = null, $merchantList = null, Zend_Date $dStartDate = null, Zend_Date $dEndDate = null){
		$totalOverview = Array();
		
		$urls = array();
	    $valuesFromExport = Oara_Utilities::cloneArray($this->_exportOverviewParameters);
	    $valuesFromExport[] = new Oara_Curl_Parameter('startyear', $dStartDate->get(Zend_Date::YEAR));
	    $valuesFromExport[] = new Oara_Curl_Parameter('startmonth', (int)$dStartDate->get(Zend_Date::MONTH) - 1);
	    $valuesFromExport[] = new Oara_Curl_Parameter('startday', $dStartDate->get(Zend_Date::DAY));
	        
	    $valuesFromExport[] = new Oara_Curl_Parameter('endyear', $dEndDate->get(Zend_Date::YEAR));
	    $valuesFromExport[] = new Oara_Curl_Parameter('endmonth', (int)$dEndDate->get(Zend_Date::MONTH) - 1);
	    $valuesFromExport[] = new Oara_Curl_Parameter('endday', $dEndDate->get(Zend_Date::DAY));
	      
	    $urls[] = new Oara_Curl_Request('https://members.cj.com/member/'.$this->_memberId.'/publisher/report/performance.do?', $valuesFromExport);
	    $exportReport = $this->_client->get($urls);
		$exportData = str_getcsv($exportReport[0],"\n");
        $num = count($exportData);
        if ($num > 1){
			$transactionArray = Oara_Utilities::transactionMapPerDay($transactionList);
	 
	        $dateArray = Oara_Utilities::daysOfDifference($dStartDate, $dEndDate);
	
	        foreach ($dateArray as $currentStartDate ){
	        	$groupMap = array();
				$urls = array();
		        $valuesFromExport = Oara_Utilities::cloneArray($this->_exportOverviewParameters);
		        $valuesFromExport[] = new Oara_Curl_Parameter('startyear', $currentStartDate->get(Zend_Date::YEAR));
		        $valuesFromExport[] = new Oara_Curl_Parameter('startmonth', (int)$currentStartDate->get(Zend_Date::MONTH) - 1);
		        $valuesFromExport[] = new Oara_Curl_Parameter('startday', $currentStartDate->get(Zend_Date::DAY));
		        
		        $valuesFromExport[] = new Oara_Curl_Parameter('endyear', $currentStartDate->get(Zend_Date::YEAR));
		        $valuesFromExport[] = new Oara_Curl_Parameter('endmonth', (int)$currentStartDate->get(Zend_Date::MONTH) - 1);
		        $valuesFromExport[] = new Oara_Curl_Parameter('endday', $currentStartDate->get(Zend_Date::DAY));
		        
		        $urls[] = new Oara_Curl_Request('https://members.cj.com/member/'.$this->_memberId.'/publisher/report/performance.do?', $valuesFromExport);
	        	
		        $exportReport = $this->_client->get($urls);
		        $clickImpressionsArray = array();
	        	if (!preg_match("/error/i", $exportReport[0], $matches)){
	        		$exportData = str_getcsv($exportReport[0],"\n");
			        $num = count($exportData);
			        for ($i = 1; $i < $num; $i++) {
			            $overviewExportArray = str_getcsv($exportData[$i],",");
			   			if (isset($this->_merchantMap[$overviewExportArray[0]]) &&
			   			    in_array((int)$this->_merchantMap[$overviewExportArray[0]], $merchantList)){
			   					
				            $obj = Array();
				            $obj['merchantId'] = $this->_merchantMap[$overviewExportArray[0]];
				            $obj['date'] = $currentStartDate->toString("yyyy-MM-dd HH:mm:ss");
				            $obj['click_number'] = $overviewExportArray[6];
			        		$obj['impression_number'] = $overviewExportArray[7];
			        		$clickImpressionsArray[] = $obj;
			   			}
		        	}
	            } else {
	            	echo "Warning overview could fail for the day ".$currentStartDate->toString("dd/MM/yyyy")." \n\n";
	            }
			        
		        $groupMap = self::groupOverview($groupMap, $clickImpressionsArray);
		        
	       		$transactionDateArray = array();
		        foreach ($transactionArray as $merchantId => $data){
		        	$transactionDateArray = array_merge($transactionDateArray, Oara_Utilities::getDayFromArray($merchantId, $transactionArray, $currentStartDate));
		        }
		                 
			    if (count($transactionDateArray) > 0 ){
			    	$groupMap = self::groupOverview($groupMap, $transactionDateArray);
			    }
	                	
	                	
		   		foreach($groupMap as $merchant => $groupMerchant){
					foreach($groupMerchant as $link => $groupLink){
						foreach($groupLink as $website => $groupWebsite){
					    	$groupWebsite['merchantId'] = $merchant;
					    	$groupWebsite['link'] = $link;
					    	$groupWebsite['website'] = $website;
					    	$groupWebsite['date'] = $currentStartDate->toString("yyyy-MM-dd HH:mm:ss");
					   		if (Oara_Utilities::checkRegister($groupWebsite)){
					        	$totalOverview[] = $groupWebsite;
					        }
				        }
				    }
			    }
        	}
        }
		return $totalOverview;  
    }
	/**
     * Group the overview
     * @param $groupMap - map where we are grouping
     * @param $registers - registers to add
     * @return none
     */
    public function groupOverview(array $groupMap = null, array $registers = null)
    {
        foreach ($registers as $register){
        	if (!isset($register['link']) || $register['link'] === null){
        		$register['link'] = '';
        	}
            if (!isset($register['website']) || $register['website'] === null){
                $register['website'] = '';
            }
            
        	if (!isset($groupMap[$register['merchantId']])){
        		$groupMap[$register['merchantId']] = array();
        	}
        	
        	if (!isset($groupMap[$register['merchantId']][$register['link']])){
        		$groupMap[$register['merchantId']][$register['link']] = array();
        	}
        	if(!isset($groupMap[$register['merchantId']][$register['link']][$register['website']])){
        		$overView = array();
        		$overView['click_number'] = 0;
        		$overView['impression_number'] = 0;
        		$overView['transaction_number'] = 0;
        		$overView['transaction_confirmed_value'] = 0;
        		$overView['transaction_pending_value'] = 0;
        		$overView['transaction_declined_value'] = 0;
        		$overView['transaction_confirmed_commission'] = 0;
                $overView['transaction_pending_commission'] = 0;
                $overView['transaction_declined_commission'] = 0;
        		
        		$groupMap[$register['merchantId']][$register['link']][$register['website']] = $overView;
        	}
        	if (isset($register['click_number'])){
        		$groupMap[$register['merchantId']][$register['link']][$register['website']]['click_number'] += (int)$register['click_number'];
        	}
            if (isset($register['impression_number'])){
                $groupMap[$register['merchantId']][$register['link']][$register['website']]['impression_number'] += (int)$register['impression_number'];
            }
            if (isset($register['status'])){
                $groupMap[$register['merchantId']][$register['link']][$register['website']]['transaction_number'] += 1;
            }
            if (isset($register['status']) && $register['status'] == Oara_Utilities::STATUS_CONFIRMED){
                $groupMap[$register['merchantId']][$register['link']][$register['website']]['transaction_confirmed_value'] += (double)$register['amount'];
                $groupMap[$register['merchantId']][$register['link']][$register['website']]['transaction_confirmed_commission'] += (double)$register['commission'];
            }
            if (isset($register['status']) && $register['status'] == Oara_Utilities::STATUS_PENDING){
                $groupMap[$register['merchantId']][$register['link']][$register['website']]['transaction_pending_value'] += (double)$register['amount'];
                $groupMap[$register['merchantId']][$register['link']][$register['website']]['transaction_pending_commission'] += (double)$register['commission'];
            }
            if (isset($register['status']) && $register['status'] == Oara_Utilities::STATUS_DECLINED){
                $groupMap[$register['merchantId']][$register['link']][$register['website']]['transaction_declined_value'] += (double)$register['amount'];
                $groupMap[$register['merchantId']][$register['link']][$register['website']]['transaction_declined_commission'] += (double)$register['commission'];
            }
        }
        return $groupMap;
    }
    /**
     * Gets all the merchants and returns them in an array.
     * @return array
     */
    private function getMerchantExport (){
    	$merchantReportList = Array();
        $valuesFromExport = $this->_exportMerchantParameters;

		
		$urls = array();
        $urls[] = new Oara_Curl_Request('https://members.cj.com/member/'.$this->_memberId.'/publisher/accounts/listmyadvertisers.do', array());                  
        $exportReport = $this->_client->get($urls);
        if (!preg_match("/Sorry, No Results Found\./", $exportReport[0], $matches)){
	        $urls = array();
	        $urls[] = new Oara_Curl_Request('https://members.cj.com/member/'.$this->_memberId.'/publisher/accounts/listmyadvertisers.do', $valuesFromExport);                  
	        $exportReport = $this->_client->post($urls);
	        $exportData = str_getcsv($exportReport[0], "\n");
	        $merchantReportList = Array();
	        $num = count($exportData);
	        for ($i = 1; $i < $num; $i++) {
	            $merchantExportArray = str_getcsv($exportData[$i], ",");
	            $merchantReportList[] = $merchantExportArray;
	        }
        }
       
        return $merchantReportList;
    }
	/**
	 * (non-PHPdoc)
	 * @see Oara/Network/Oara_Network_Base#getPaymentHistory()
	 */
	public function getPaymentHistory(){
    	$paymentHistory = array();
    	
    	$urls = array();
    	$urls[] = new Oara_Curl_Request('https://members.cj.com/member/'.$this->_memberId.'/publisher/displaypublisherpaymentstatus.do?ispopup=true', array());   
    	$exportReport = $this->_client->get($urls);
        if (preg_match("/\/publisher\/getpublisherpaymenthistory\.do/", $exportReport[0], $matches)){
	    	$urls = array();
			$valuesFromExport = $this->_exportPaymentParameters;
	        $urls[] = new Oara_Curl_Request('https://members.cj.com/member/'.$this->_memberId.'/publisher/getpublisherpaymenthistory.do?', $valuesFromExport);
	        $exportReport = $this->_client->get($urls);
	        $exportData = str_getcsv($exportReport[0],"\n");
		    $num = count($exportData);
		    for ($j = 1; $j < $num; $j++) {
		    	$paymentData = str_getcsv($exportData[$j],",");
		    	$obj = array();
		    	$date = new Zend_Date($paymentData[0], "dd-MMM-yyyy HH:mm", 'en_US');
				$obj['date'] = $date->toString("yyyy-MM-dd HH:mm:ss");
		    	$obj['value'] = Oara_Utilities::parseDouble($paymentData[1]);
		    	$obj['method'] = $paymentData[2];
		    	$obj['pid'] = $paymentData[6];
		    	$paymentHistory[] = $obj;
		    }
        }
    	return $paymentHistory;
    }
    /**
	 * (non-PHPdoc)
	 * @see Oara/Network/Oara_Network_Base#getCreatives()
	 */
	public function getCreatives(){
		$creativesMap = array();
		$pageSize = 100;
    	$restUrl = 'https://linksearch.api.cj.com/v2/link-search?website-id=5269810&advertiser-ids=joined&page-number=1&records-per-page='.$pageSize;
        //Setting the client.
		$client = new Zend_Http_Client($restUrl);
        $client->setHeaders('Authorization',$this->_apiPassword);
		$response = $client->request('GET');
    	$xml = simplexml_load_string($response->getBody(), null, LIBXML_NOERROR | LIBXML_NOWARNING);
    	$linksNumber = self::findAttribute($xml->links->attributes(), 'total-matched');
    	
    	$iteration = self::calculeIterationNumber($linksNumber, $pageSize);
		for($j = 1; $j < $iteration; $j++){
			$restUrl = 'https://linksearch.api.cj.com/v2/link-search?website-id=5269810&advertiser-ids=joined&page-number='.$j.'&records-per-page='.$pageSize;
	        //Setting the client.
			$client = new Zend_Http_Client($restUrl);
	        $client->setHeaders('Authorization',$this->_apiPassword);
			$response = $client->request('GET');
	    	$xml = simplexml_load_string($response->getBody(), null, LIBXML_NOERROR | LIBXML_NOWARNING);
	    	foreach ($xml->links->link as $link){
	    		$creativesMap[self::findAttribute($link, 'advertiser-id')][] = self::findAttribute($link, 'link-code-html');
	    		//$link->link-code-javascript;
	    		
	    	}
		}
		return $creativesMap;
	}
	/**
     * Calculate the number of iterations needed
     * @param $rowAvailable
     * @param $rowsReturned
     */
	private function calculeIterationNumber($rowAvailable, $rowsReturned){
		$iterationDouble = (double)($rowAvailable/$rowsReturned);
		$iterationInt = (int)($rowAvailable/$rowsReturned);
		if($iterationDouble > $iterationInt){
			$iterationInt++;
		}
		return $iterationInt;
	}
	/**
	 * Cast the XMLSIMPLE object into string
	 * @param $object
	 * @param $attribute
	 * @return unknown_type
	 */
	private function findAttribute( $object = null, $attribute = null) {
		$return = null;
		$return = trim($object->$attribute);
		return $return;
	}
}