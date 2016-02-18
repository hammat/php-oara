<?php
namespace Oara\Network\Publisher;
    /**
     * The goal of the Open Affiliate Report Aggregator (OARA) is to develop a set
     * of PHP classes that can download affiliate reports from a number of affiliate networks, and store the data in a common format.
     *
     * Copyright (C) 2016  Fubra Limited
     * This program is free software: you can redistribute it and/or modify
     * it under the terms of the GNU Affero General Public License as published by
     * the Free Software Foundation, either version 3 of the License, or any later version.
     * This program is distributed in the hope that it will be useful,
     * but WITHOUT ANY WARRANTY; without even the implied warranty of
     * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
     * GNU Affero General Public License for more details.
     * You should have received a copy of the GNU Affero General Public License
     * along with this program.  If not, see <http://www.gnu.org/licenses/>.
     *
     * Contact
     * ------------
     * Fubra Limited <support@fubra.com> , +44 (0)1252 367 200
     **/
/**
 * Export Class
 *
 * @author     Carlos Morillo Merino
 * @category   AffiliateGateway
 * @copyright  Fubra Limited
 * @version    Release: 01.00
 *
 */
class AffiliateGateway extends \Oara\Network
{
    private $_client = null;
    protected $_extension = null;

    /**
     * @param $credentials
     * @throws \Exception
     */
    public function login($credentials)
    {
        $user = $credentials['user'];
        $password = $credentials['password'];
        $this->_client = new \Oara\Curl\Access($credentials);

        $valuesLogin = array(
            new \Oara\Curl\Parameter('username', $user),
            new \Oara\Curl\Parameter('password', $password)
        );
        $loginUrl = "{$this->_extension}/login.html";

        $urls = array();
        $urls[] = new \Oara\Curl\Request($loginUrl, $valuesLogin);
        $this->_client->post($urls);
    }

    /**
     * @return array
     */
    public function getNeededCredentials()
    {
        $credentials = array();

        $parameter = array();
        $parameter["user"]["description"] = "User Log in";
        $parameter["user"]["required"] = true;
        $credentials[] = $parameter;

        $parameter = array();
        $parameter["password"]["description"] = "Password to Log in";
        $parameter["password"]["required"] = true;
        $credentials[] = $parameter;

        $parameter = array();
        $parameter["network"]["description"] = "Which Network to be used ['uk','au']";
        $parameter["password"]["required"] = true;
        $credentials[] = $parameter;

        return $credentials;
    }

    /**
     * @return bool
     */
    public function checkConnection()
    {
        $connection = false;
        $urls = array();
        $urls[] = new \Oara\Curl\Request("{$this->_extension}/affiliate_home.html", array());
        $exportReport = $this->_client->get($urls);

        $doc = new \DOMDocument();
        @$doc->loadHTML($exportReport[0]);
        $xpath = new \DOMXPath($doc);
        $results = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " logout ")]');
        if ($results->length > 0) {
            $connection = true;
        }
        return $connection;
    }

    /**
     * @return array
     */
    public function getMerchantList()
    {
        $merchants = array();

        $valuesFromExport = array();
        $valuesFromExport[] = new \Oara\Curl\Parameter('p', "");
        $valuesFromExport[] = new \Oara\Curl\Parameter('time', "1");
        $valuesFromExport[] = new \Oara\Curl\Parameter('changePage', "");
        $valuesFromExport[] = new \Oara\Curl\Parameter('oldColumn', "programmeId");
        $valuesFromExport[] = new \Oara\Curl\Parameter('sortField', "programmeId");
        $valuesFromExport[] = new \Oara\Curl\Parameter('order', "up");
        $valuesFromExport[] = new \Oara\Curl\Parameter('records', "-1");
        $urls = array();
        $urls[] = new \Oara\Curl\Request("{$this->_extension}/affiliate_program_active.html?", $valuesFromExport);
        $exportReport = $this->_client->get($urls);


        $doc = new \DOMDocument();
        @$doc->loadHTML($exportReport[0]);
        $xpath = new \DOMXPath($doc);
        $tableList = $xpath->query('//table[@class="bluetable"]');


        $exportData = self::htmlToCsv(self::DOMinnerHTML($tableList->item(0)));
        $num = count($exportData);
        for ($i = 4; $i < $num; $i++) {
            $merchantExportArray = \str_getcsv($exportData[$i], ";");
            if ($merchantExportArray[0] != "No available programs.") {
                $obj = array();
                $obj['cid'] = $merchantExportArray[0];
                $obj['name'] = $merchantExportArray[2];
                $merchants[] = $obj;
            }

        }
        return $merchants;
    }

    /**
     * @param $html
     * @return array
     */
    private function htmlToCsv($html)
    {
        $html = str_replace(array(
            "\t",
            "\r",
            "\n"
        ), "", $html);
        $csv = "";

        $doc = new \DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new \DOMXPath($doc);
        $results = $xpath->query('//tr');
        foreach ($results as $result) {

            $doc = new \DOMDocument();
            @$doc->loadHTML(self::DOMinnerHTML($result));
            $xpath = new \DOMXPath($doc);
            $resultsTd = $xpath->query('//td');
            $countTd = $resultsTd->length;
            $i = 0;
            foreach ($resultsTd as $resultTd) {
                $value = $resultTd->nodeValue;
                if ($i != $countTd - 1) {
                    $csv .= \trim($value) . ";";
                } else {
                    $csv .= \trim($value);
                }
                $i++;
            }
            $csv .= "\n";
        }
        $exportData = \str_getcsv($csv, "\n");
        return $exportData;
    }

    /**
     * @param $element
     * @return string
     */
    private function DOMinnerHTML($element)
    {
        $innerHTML = "";
        $children = $element->childNodes;
        foreach ($children as $child) {
            $tmp_dom = new \DOMDocument ();
            $tmp_dom->appendChild($tmp_dom->importNode($child, true));
            $innerHTML .= trim($tmp_dom->saveHTML());
        }
        return $innerHTML;
    }

    /**
     * @param null $merchantList
     * @param \DateTime|null $dStartDate
     * @param \DateTime|null $dEndDate
     * @return array
     */
    public function getTransactionList($merchantList = null, \DateTime $dStartDate = null, \DateTime $dEndDate = null)
    {

        $merchantNameMap = \Oara\Utilities::getMerchantNameMapFromMerchantList($merchantList);
        $totalTransactions = array();

        $valuesFromExport = array();
        $valuesFromExport[] = new \Oara\Curl\Parameter('period', '8');
        $valuesFromExport[] = new \Oara\Curl\Parameter('websiteId', '-1');
        $valuesFromExport[] = new \Oara\Curl\Parameter('merchantId', '-1');
        $valuesFromExport[] = new \Oara\Curl\Parameter('subId', '');
        $valuesFromExport[] = new \Oara\Curl\Parameter('approvalStatus', '-1');
        $valuesFromExport[] = new \Oara\Curl\Parameter('records', '20');
        $valuesFromExport[] = new \Oara\Curl\Parameter('sortField', 'purchDate');
        $valuesFromExport[] = new \Oara\Curl\Parameter('time', '1');
        $valuesFromExport[] = new \Oara\Curl\Parameter('p', '1');
        $valuesFromExport[] = new \Oara\Curl\Parameter('changePage', '1');
        $valuesFromExport[] = new \Oara\Curl\Parameter('oldColumn', 'purchDate');
        $valuesFromExport[] = new \Oara\Curl\Parameter('order', 'down');
        $valuesFromExport[] = new \Oara\Curl\Parameter('mId', '-1');
        $valuesFromExport[] = new \Oara\Curl\Parameter('submittedSubId', '');
        $valuesFromExport[] = new \Oara\Curl\Parameter('exportType', 'csv');
        $valuesFromExport[] = new \Oara\Curl\Parameter('reportTitle', 'report');

        $valuesFromExport[] = new \Oara\Curl\Parameter('startDate', $dStartDate->format("d/m/Y"));
        $valuesFromExport[] = new \Oara\Curl\Parameter('endDate', $dEndDate->format("d/m/Y"));

        $urls = array();
        $urls[] = new \Oara\Curl\Request("{$this->_extension}/affiliate_statistic_transaction.html?", $valuesFromExport);


        $exportReport = $this->_client->get($urls);
        $exportData = \str_getcsv($exportReport[0], "\n");
        $num = \count($exportData);
        for ($i = 1; $i < $num; $i++) {
            $transactionExportArray = str_getcsv($exportData[$i], ",");
            if (isset($merchantNameMap[$transactionExportArray[1]])) {
                $merchantId = $merchantNameMap[$transactionExportArray[1]];

                $transaction = Array();
                $transaction['merchantId'] = $merchantId;
                $transactionDate = \DateTime::createFromFormat("d/m/Y H:i:s", $transactionExportArray[4]);
                $transaction['date'] = $transactionDate->format("Y-m-d H:i:s");
                $transaction['unique_id'] = $transactionExportArray[0];

                if ($transactionExportArray[11] != null) {
                    $transaction['custom_id'] = $transactionExportArray[11];
                }

                if ($transactionExportArray[12] == "Approved" || $transactionExportArray[12] == "Approve") {
                    $transaction['status'] = \Oara\Utilities::STATUS_CONFIRMED;
                } else
                    if ($transactionExportArray[12] == "Pending") {
                        $transaction['status'] = \Oara\Utilities::STATUS_PENDING;
                    } else
                        if ($transactionExportArray[12] == "Declined" || $transactionExportArray[12] == "Rejected") {
                            $transaction['status'] = \Oara\Utilities::STATUS_DECLINED;
                        } else {
                            throw new \Exception ("No Status found " . $transactionExportArray[12]);
                        }
                $transaction['amount'] = \Oara\Utilities::parseDouble($transactionExportArray[7]);
                $transaction['commission'] = \Oara\Utilities::parseDouble($transactionExportArray[9]);
                $totalTransactions[] = $transaction;
            }

        }

        return $totalTransactions;
    }

    /**
     * @return array
     */
    public function getPaymentHistory()
    {
        $paymentHistory = array();

        $urls = array();
        $urls[] = new \Oara\Curl\Request("{$this->_extension}/affiliate_invoice.html?", array());
        $exportReport = $this->_client->get($urls);

        $doc = new \DOMDocument();
        @$doc->loadHTML($exportReport[0]);
        $xpath = new \DOMXPath($doc);
        $tableList = $xpath->query('//table[@class="bluetable"]');
        $exportData = self::htmlToCsv(self::DOMinnerHTML($tableList->item(0)));
        $num = \count($exportData);
        for ($i = 4; $i < $num; $i++) {
            $paymentExportArray = \str_getcsv($exportData[$i], ";");
            if (\count($paymentExportArray) > 7) {
                $obj = array();
                $date = \DateTime::createFromFormat("d/m/Y", $paymentExportArray[1]);
                $obj['date'] = $date->format("Y-m-d H:i:s");
                $obj['pid'] = $paymentExportArray[0];
                $obj['method'] = 'BACS';
                $obj['value'] = \Oara\Utilities::parseDouble($paymentExportArray[4]);
                $paymentHistory[] = $obj;
            }

        }
        return $paymentHistory;
    }

}
