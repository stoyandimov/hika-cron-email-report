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
        // Sets 'From' email address
        $fromEmail = $this->params->get('from_email', null);
        if (!$fromEmail) {
            JLog::add("No 'from email' set for " . __METHOD__, JLog::CRITICAL);
            throw new JException("No 'from email' set for " . __METHOD__);
            return true;
        }
        $fromName  = $this->params->get('from_name', $fromEmail);
        
        // Sets 'To' email address
        $to = $this->getEmailsFromParams();
        if (!$to) {
            JLog::add("No 'to email(s)' set for " . __METHOD__, JLog::CRITICAL);
            throw new JException("No 'to email(s)' set for " . __METHOD__);
            return true;
        }
        
        // The data of the meals (always tomorrow)
        $date = time() + (60 * 60 * 24);
        $strDate  = strftime("%d/%m/%Y", $date);
        
        // Sets email subject
        $emailSubject = $this->params->get("email_subject", "Cron Report for " . $strDate);
        
        $htmlLayout = $this->getLayout(
            $date, 
            $this->getProductsByDateWidget($strDate), 
            $this->getOrderByDateWidget($strDate)
        );
        
        // echo $htmlLayout; return true; // DEBUGGER :)
        
        $send = $this->sendEmail($fromEmail, $fromName, $to, $htmlLayout, $emailSubject);
        if ($send === true) {
            JLog::add("Email successfully sent in " . __METHOD__, JLog::INFO);
        } else {
            JLog::add("Sending email failed for unknown reason in " . __METHOD__, JLog::CRITICAL);
            throw new JException("Sending email failed in " . __METHOD__ . ": " . $send->__toString());
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
         
         $emails = explode(";", $strEmails);
         return $emails;
    }      
    
    /**
     * Sends the report via email to a list of recipient
     * 
     * @param string $fromEmail The email
     * @param string $fromName  The name
     * @param array  $to        Array of recipients emails
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
        $mailer->Encoding = 'base64';
        $mailer->setBody($body);
        $mailer->setSubject($subject);
        
        return $mailer->Send();
    }
    
    
    // HTML LAYOUT METHODS
    
    /**
     * Returns the complete layout for the email body
     * 
     * @param string $date E.g. 13/08/2014
     * @param string $productsHTML
     * @param string $ordersHTML
     * @return string Layout
     */
    protected function getLayout($date, $productsHTML, $ordersHTML)
    {
        return '<!DOCTYPE html>
                <html>
                    <head>
                        <meta charset="utf-8" />
                        <title>Cron email report</title>
                        <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css">
                        <script src="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/js/bootstrap.min.js"></script>
                    </head>
                    <body>
                        <section class="container">
                            <header class="row jumbotron">
                                <hgroup>
                                    <h2>Hikashop Cron Email Report</h2>
                                    <h3>for ' . strftime("%A (%d/%m/%Y)", $date) . '</h3>
                                </hgroup>
                            </header>
                            <article class="well row">
                                <section  class="col-md-8">
                                    ' . $ordersHTML . '
                                </section>
                                <section  class="col-md-4">
                                    ' . $productsHTML . '
                                </section>
                            </article>
                            <footer class="well row">
                                <section>
                                    <h5 class="text-right">Found a bug? Report it on the <a href="https://github.com/stoyandimov/hika-cron-email-report/issues">Github Issue Tracker</a></h5>
                                </section>
                            </footer> 
                        </section>
                    </body>
                </html>';
    }
    
    /**
     * Returns the products and quantities HTML or empty string
     * 
     * @param type $date
     * @return string The products/quantities HTML or empty string
     */
    protected function getProductsByDateWidget($date)
    {
        // Iterate over results and prep HTML
        $html  = '  <div class="panel panel-default">';
        $html .= '      <div class="panel-heading"><h4>Products</h4></div>';
        $html .= '      <ul class="list-group" style="margin-left: 0">';
        $html .= "      {$this->getProductsByDateWidgetInner("WHERE hp.date = '{$date}'")}";
        $html .= '      </ul>';
        $html .= '  </div>';
        
        return $html;
    }
    
    /**
     * Returns the HTML content for the products section
     * 
     * @param string $date  E.g. 13/08/2014
     * @param string $where The where clouse for the product's SELECT query 
     * @return string HTML
     */
    protected function getProductsByDateWidgetInner($where) 
    {
        // Prep and execute query
        $query  = "SELECT ";
	$query .= " 	hop.order_product_name      AS productName, ";
        $query .= " 	hp.product_id               AS product_id, ";
	$query .= " 	SUM(hop.order_product_quantity) AS productQuantity ";
	$query .= " FROM  #__hikashop_order_product hop ";
	$query .= " INNER JOIN #__hikashop_order   ho ON ho.order_id   = hop.order_id ";
	$query .= " INNER JOIN #__hikashop_product hp ON hp.product_id = hop.product_id ";
	$query .= " {$where} ";
	$query .= " GROUP BY hop.order_product_name ";
        $db = JFactory::getDbo();
        $db->setQuery($query);
        $resultSet = $db->execute();
        
        $html = "";
        if (mysql_num_rows($resultSet) != 0) {
            while($row = mysql_fetch_assoc($resultSet)) {
                $html .= '<li class="list-group-item">';
                $html .= "  <span class='badge pull-left product-id-{$row['product_id']}'>{$row['productQuantity']}</span> &nbsp;";                
                $html .= "  {$row['productName']}";
                $html .= '</li>';
            }
        } else {
            $html .= '  <li class="list-group-item">';
            $html .= '      <div class="alert alert-info">';
            $html .= "          <i class='glyphicon glyphicon-info-sign'></i>";
            $html .= "          &nbsp; No products";
            $html .= '      </div>';            
            $html .= '  </li>';
        }
        
        return $html;
    }
    
    /**
     * Returns the HTML content for the orders section
     * 
     * @param string $date E.g. 13/08/2014
     * @return string HTML
     */
    protected function getOrderByDateWidget($date) 
    {
        //$productObject = $this->getProductObject();
        //get the product object and load the product with the IDs from $order->products
        
        // Prep and execute query
        $query  = "SELECT ";
        $query .= " 	ho.order_id                 AS order_id, ";
        
        $query .= "     ho.order_created            AS order_created,";
        
        $query .= " 	ha.address_title            AS title, ";
	$query .= " 	ha.address_firstname        AS firstName, ";
        $query .= " 	ha.address_middle_name      AS middleName, ";
        $query .= " 	ha.address_lastname         AS lastName, ";
        
        $query .= " 	ha.address_company          AS companyStreet, ";
        $query .= " 	ha.address_street           AS street, ";
        $query .= " 	ha.address_post_code        AS postcode, ";
        $query .= " 	ha.address_state            AS state, ";
       
        $query .= " 	ha.address_telephone        AS telephone ";
	$query .= " FROM  #__hikashop_order_product hop ";
	$query .= " INNER JOIN #__hikashop_order   ho ON ho.order_id   = hop.order_id ";
	$query .= " INNER JOIN #__hikashop_product hp ON hp.product_id = hop.product_id ";
        $query .= " INNER JOIN #__hikashop_address ha ON ha.address_id = ho.order_shipping_address_id ";
	$query .= " WHERE hp.date = '{$date}' ";
        $query .= " ORDER BY ho.order_created DESC";

        
        $db = JFactory::getDbo();
        $db->setQuery($query);
        $resultSet = $db->execute();
        
        // Iterate over results and prep HTML
        $html  = '  <div class="panel panel-default">';
        $html .= '      <ul class="list-group" style="margin-left: 0">';
        $html .= '          <li class="list-group-item"><h4>Orders</h4></li>';
        $html .= '      </ul>';
        $html .= '  </div>';
        if (mysql_num_rows($resultSet) != 0) {
            while($row = mysql_fetch_assoc($resultSet)) {
                $html .= '<div class="panel panel-default">';
                $html .= '  <ul class="list-group" style="margin-left: 0">';
                $html .= '  <li class="list-group-item">';
                $html .= "      <strong>{$row['title']}. {$row['firstName']} {$row['middleName']} {$row['lastName']}</strong>";
                $html .= "      <br />";
                $html .= "      Order created: " . strftime("%A (%d/%m/%Y)", $row['order_created']);
                $html .= "      <br />";
                $html .=        $row['companyStreet'] == "" ? "Address: " : "Company Address: ";
                $html .=        $row['companyStreet'] == "" ?  $row['street'] :  $row['companyStreet'];
                $html .= "      , {$row['postcode']} {$row['state']}";
                $html .= "      <br />";
                $html .= "      Telephone: {$row['telephone']} ";
                $html .= '  </li>';
                
                $html .= $this->getProductsByDateWidgetInner("WHERE ho.order_id = '{$row['order_id']}' AND hp.date = '{$date}'");
                $html .= '  </ul>';
                $html .= '</div>';
            }
        } else {
            $html .= '  <li class="list-group-item">';
            $html .= '      <div class="alert alert-info">';
            $html .= "          <i class='glyphicon glyphicon-info-sign'></i>";
            $html .= "          &nbsp; No orders";
            $html .= '      </div>';            
            $html .= '  </li>';
        }
        
        return $html;
    }
   
}