<?php
/**
    At 23:00 hrs automatic generated e-mail with the following information.

    Day - X (e.g. 19/07/2014)
    ------------------------------
    Meal - A - total amount
    Meal - B - total amount
    -------------------------------

    Customer1 
    Meal - A - X
    Meal - B - X
    Address: Lorem ipsum
    Telephone : 555-555-555
    ---------------------------------------------

    Customer2 
    Meal - A - X
    Meal - B - X
    Address: Lorem ipsum
    Telephone : 555-555-555
    ---------------------------------------------

    ETC.
 */

class plgHikashopCron_Email_Report extends JPlugin 
{
    
    /**
     * Preps plugin
     * 
     * @param type $subject
     * @param type $config
     */
    function plgHikashopCron_Email_Report(&$subject, $config)
    {
        parent::__construct($subject, $config);
    }
    
    /**
     * Plugin trigger to send email
     * 
     * @param type $messages
     * @return boolean
     * @throws JException
     */
    function onHikashopCronTrigger(&$messages) 
    {
        // The data of the meals (always tomorrow)
        $date  = strftime("%d/%m/%Y", time() + (60 * 60 * 24));
        // Get the orders for the last 4 weeks
        $lastWeeks = (int) $this->params->get("lastWeeks", 4);
        $since = time() - ((int) $lastWeeks * 7 * 24 * 60 * 60);
        //$orders = $this->getOrdersForTomorrow($date, $lastWeeks);
        
        
        
        $fromEmail = $this->params->get('from_email', null);
        if (!$fromEmail) {
            throw new JException("No 'from email' set for " . __METHOD__);
        }
        $fromName  = $this->params->get('from_name', $fromEmail);
        
        
        $to = $this->getEmailsFromParams();
        if (!$to) {
            throw new JException("No 'to email(s)' set for " . __METHOD__);
        }
        $emailSubject = $this->params->get("email_subject");

        // Monday - (2014-06-02)
        $emailBody  = $this->renderDay(time() + (60 * 60 * 24));
        
        if(count(1) > 0) {
            // Prodcut1 - 25
            // Product2 - 23
            $emailBody .= $this->renderProducts($date, $since);
            
            // $emailBody .= $this->renderOrders($orders, $date);
        } else {
            $emailBody .= $this->renderNoOrders();
        }
        
        echo $emailBody;
        return true;
        if ($this->sendEmail($fromEmail, $fromName, $to, $emailBody, $emailSubject)) {
            
        } else {
            
        }
        return true;
    }
    
    /** 
     * @return array Of email addresses or null if no emails set
     */
    protected function getEmailsFromParams() {
         $strEmails = $this->params->get("to_emails", null);
         if (!$strEmails) {
             return null;
         }
         
         $emails = explode($strEmails, ";");
         return $emails;
    }
        
    protected function getProductsCountFromOrders($productId, array $orders)
    {
        $count = 0;
        foreach ($orders as $order) {
                echo $order;

            foreach ($order->cart->products as $product) {
                if ((int)$product->product_id == (int) $productId) {
                    $count += (int) $product->order_product_quantity;
                }
            }
        }
        
        return $count;
    }
    
   
    
    /**
     * Returns array of order_ids
     * 
     * @param array $orders
     * @return array Order Ids
     */
    protected function getOrderIds(array $orders) 
    {
        $ids = array();
        foreach ($orders as $order) {
            $ids[] = $order->order_id;
        }
        
        return $ids;
    }
    
    /**
     * Returns all the products in the orders set
     * 
     * @param array $orders The orders
     * @return array $products or null when 0 products
     */
    protected function getProductsFromOrder(stdClass $order) 
    {
        $products = array();
        foreach ($order->cart->products as $product) {
            $products[$product->product_id] = $product;
        }
        
        return count($products) > 0 ? $products : null;
    }
      
    /**
     * Returns all the orders within a period. If no orders returns null
     * 
     * @param timestamp $periodBegin Begin Period
     * @param timestamp $periodEnd   End Period
     * @return array    $orders or null when 0 orders
     */
    protected function getOrdersForTomorrow($date, $since) 
    {
        
        
        // Query getting all the orders within the $lastWeeks
//        $query  = "SELECT ho.order_id ";
//        $query .= " FROM #__hikashop_order ho ";           
//        $query .= " WHERE ho.order_created > " . $since;           

        $query  = "SELECT ";
	$query .= " 	hop.order_product_name AS product, ";
	$query .= " 	COUNT(hop.order_product_quantity) AS count ";
	$query .= " FROM  #__hikashop_order_product hop ";
	$query .= " INNER JOIN #__hikashop_order   ho ON ho.order_id   = hop.order_id ";
	$query .= " INNER JOIN #__hikashop_product hp ON hp.product_id = hop.product_id ";
	$query .= " WHERE ho.order_created >= {$since} ";
        $query .= "   AND hp.date = '{$date}' ";
	$query .= " GROUP BY hop.order_product_name ";
        
        // Executing query
        $db = JFactory::getDbo();
        $db->setQuery($query);
        $resultSet = $db->execute();
        
        // Preparing orders array
        $orderObject = $this->getOrderObject();
        $orders = array();
        if ($resultSet) {
            while($row = mysql_fetch_assoc($resultSet)) {
                $order = $orderObject->loadFullOrder($row['order_id'], true, false);
                if ($order) {
                    $orders[] = $order;
                }
            }
        }
        
        return count($orders) > 0 ? $orders : null;
    }
    
    /**
     * Sends the report via email to a list of recipient
     * 
     * @param string $fromEmail The email
     * @param string $fromName  The name
     * @param array $to         Array of recipients emails
     * @param string $body      The HTML email body
     * @param string $subject   Subject [Optional $subject = "Hekashop Cron Report"]
     * @return boolean          True on success, otherwise false
     */
    protected function sendEmail($fromEmail, $fromName, array $to, $body, $subject = "Hekashop Cron Report") 
    {
        $mailer = JFactory::getMailer();
        foreach ($to as $email) {
            $mailer->addRecipient($email);
        }
        $mailer->setFrom($fromEmail, $fromName);
        $mailer->isHtml(true);
        $mailer->setBody($body);
        $mailer->setSubject($subject);
        
        return $mailer->Send();
    }
    
    // HTML LAYOUT METHODS
    
    /**
     * @param type $date
     * @return string Header with date (html)
     */
    protected function renderDay($date) 
    {
         $dateString = strftime("%A (%d/%m/%Y)", $date);
         return "<h1>{$dateString}</h1>";
    }
    
    protected function renderNoOrders() 
    {
        return "<div class='alert alert-info'>"
                . "<i class='glyphicon glyphicon-info-sign'></i>"
                . "&nbsp; No orders for {$date}"
             . "</div>";
    }
    
    protected function renderProducts($date, $since)
    {
        // Prep and execute query
        $query  = "SELECT ";
	$query .= " 	hop.order_product_name AS productName, ";
	$query .= " 	COUNT(hop.order_product_quantity) AS productCount ";
	$query .= " FROM  #__hikashop_order_product hop ";
	$query .= " INNER JOIN #__hikashop_order   ho ON ho.order_id   = hop.order_id ";
	$query .= " INNER JOIN #__hikashop_product hp ON hp.product_id = hop.product_id ";
	$query .= " WHERE ho.order_created >= {$since} "; 
        $query .= "   AND hp.date = '{$date}' ";
	$query .= " GROUP BY hop.order_product_name ";
        
        $db = JFactory::getDbo();
        $db->setQuery($query);
        $resultSet = $db->execute();
        
        // Iterate over results and prep HTML
        $html = "";
        if ($resultSet) {
            $html = '<h2>Products</h2>';
            $html .= '<ul class="list-group">';
            while($row = mysql_fetch_assoc($resultSet)) {
                $html .= '  <li class="list-group-item">';
                $html .= "      {$row['productName']}";
                $html .= "      <span class='badge'>{$row['productCount']}</span>";
                $html .= '  </li>';
            }
             $html .= '</ul>';
        }
        
        return $html;
    }
    
    protected function renderOrders(array $orders) 
    {
        $productObject = $this->getProductObject();
        //get the product object and load the product with the IDs from $order->products
        foreach ($orders as $order) {
            foreach ($order->products as $product) {
                $prod = $productObject->get($product->product_id);
                var_dump($prod->date);
            }
        }
    }
    
    
    // FACTGORY METHODS
    
    /**
     * Get a Hika order object
     * 
     * @return hikashopOrderClass
     */
    protected function getOrderObject() {
        return hikashop_get('class.order');
    }
    
    /**
     * Get a Hika product object
     * 
     * @return hikashopProductClass
     */
    protected function getProductObject() {
        return hikashop_get('class.product');
    }
}
