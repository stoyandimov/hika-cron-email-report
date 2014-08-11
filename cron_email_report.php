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
            throw new JException("No 'from email' set for " . __METHOD__);
        }
        $fromName  = $this->params->get('from_name', $fromEmail);
        
        // Sets 'To' email address
        $to = $this->getEmailsFromParams();
        if (!$to) {
            throw new JException("No 'to email(s)' set for " . __METHOD__);
        }
        
        // The data of the meals (always tomorrow)
        $date = time() + (60 * 60 * 24);
        $strDate  = strftime("%d/%m/%Y", $date);
        
        // Sets email subject
        $emailSubject = $this->params->get("email_subject", "Cron Report for " . $strDate);
        
        $htmlLayout = $this->getLayout(
            $date, 
            $this->renderProductsByDate($strDate), 
            $this->renderOrderByDate($strDate)
        );
        
        echo $htmlLayout;
        
        return true;
        if ($this->sendEmail($fromEmail, $fromName, $to, $htmlLayout, $emailSubject)) {
            
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
                        <article class="container">
                            <section>
                                <hgroup>
                                    <h2>Hikashop Cron Email Report</h2>
                                    <h3>' . strftime("%A (%d/%m/%Y)", $date) . '</h3>
                                </hgroup>
                            </section>
                            <section>
                                ' . $productsHTML . '
                            </section>
                            <section>
                                ' . $ordersHTML . '
                            </section>
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
    protected function renderProductsByDate($date)
    {
        // Prep and execute query
        $query  = "SELECT ";
	$query .= " 	hop.order_product_name AS productName, ";
	$query .= " 	SUM(hop.order_product_quantity) AS productQuantity ";
	$query .= " FROM  #__hikashop_order_product hop ";
	$query .= " INNER JOIN #__hikashop_order   ho ON ho.order_id   = hop.order_id ";
	$query .= " INNER JOIN #__hikashop_product hp ON hp.product_id = hop.product_id ";
	$query .= " WHERE hp.date = '{$date}' ";
	$query .= " GROUP BY hop.order_product_name ";
        $db = JFactory::getDbo();
        $db->setQuery($query);
        $resultSet = $db->execute();
        
        // Iterate over results and prep HTML
        $html  = '<div class="panel panel-default">';
        $html .= '  <div class="panel-heading">Products</div>';
        $html .= '  <ul class="list-group" style="margin-left: 0">';
        if (mysql_num_rows($resultSet) != 0) {
            while($row = mysql_fetch_assoc($resultSet)) {
                $html .= '<li class="list-group-item">';
                $html .= "  <span class='badge pull-left'>{$row['productQuantity']}</span> &nbsp;";                
                $html .= "  {$row['productName']}";
                $html .= '</li>';
            }
        } else {
            $html .= '  <li class="list-group-item">';
            $html .= '      <div class="alert alert-info">';
            $html .= "          <i class='glyphicon glyphicon-info-sign'></i>";
            $html .= "          &nbsp; No products for {$date}";
            $html .= '      </div>';            
            $html .= '  </li>';
        }
        $html .= '  </ul>';
        $html .= '</div>';
        
        return $html;
    }

    protected function renderOrderByDate($date) 
    {
        //$productObject = $this->getProductObject();
        //get the product object and load the product with the IDs from $order->products
        
        // Prep and execute query
        $query  = "SELECT ";
        $query .= " 	ha.address_title            AS title, ";
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
        
        $db = JFactory::getDbo();
        $db->setQuery($query);
        $resultSet = $db->execute();
        
        // Iterate over results and prep HTML
        $html  = '<div class="panel panel-default">';
        $html .= '  <div class="panel-heading">Orders</div>';
        $html .= '  <ul class="list-group" style="margin-left: 0">';
        if (mysql_num_rows($resultSet) != 0) {
            while($row = mysql_fetch_assoc($resultSet)) {
                $html .= '  <li class="list-group-item active">';
                $html .= "      {$row['title']}. {$row['firstName']} {$row['middleName']} {$row['lastName']}";
                $html .=        $row['companyStreet'] == "" ? "Address: " : "Company Address: ";
                $html .=        $row['companyStreet'] == "" ?  $row['street'] :  $row['companyStreet'];
                $html .= "      , {$row['postcode']} {$row['state']}";

                $html .= "      Telephone: {$row['telephone']} ";
                $html .= '  </li>';
            }
        } else {
            $html .= '  <li class="list-group-item">';
            $html .= '      <div class="alert alert-info">';
            $html .= "          <i class='glyphicon glyphicon-info-sign'></i>";
            $html .= "          &nbsp; No products for {$date}";
            $html .= '      </div>';            
            $html .= '  </li>';
        }
        $html .= '  </ul>';
        $html .= '</div>';

       
        /*
         <div class="panel panel-default">
                                <div class="panel-heading">Orders</div>
                                <div class="panel-body">
                                    
                                </div>
                            </div>*/
        
        return $html;
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
