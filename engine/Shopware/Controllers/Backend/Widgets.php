<?php
/**
 * Shopware 4
 * Copyright © shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

/**
 * Backend widget controller
 */
class Shopware_Controllers_Backend_Widgets extends Shopware_Controllers_Backend_ExtJs
{
    /**
     * Returns the list of active widgets for the current logged
     * in user as an JSON string.
     *
     * @public
     * @return void
     */
    public function getWidgetsAction()
    {
        $auth = Shopware()->Auth();

        if (!$auth->hasIdentity()) {
            $this->View()->assign(array('success' => false));
        }

        $identity = $auth->getIdentity();
        $userID = (int) $identity->id;

        $builder = Shopware()->Models()->createQueryBuilder();
        $builder->select(array('widget', 'view'))
            ->from('Shopware\Models\Widget\Widget', 'widget')
            ->leftJoin('widget.views', 'view', 'WITH', 'view.authId = ?1')
            ->orderBy('view.position')
            ->setParameter(1, $userID);

        $data = $builder->getQuery()->getArrayResult();

        $this->View()->assign(array('success' => !empty($data), 'data' => $data));
    }

    public function saveWidgetPositionAction()
    {
        $request = $this->Request();
        $column = $request->getParam('column');
        $position = $request->getParam('position');
        $id = $request->getParam('id');
        $auth = Shopware()->Auth();

        if (!$auth->hasIdentity()) {
            $this->View()->assign(array('success' => false));
            return;
        }

        $model = Shopware()->Models()->find('Shopware\Models\Widget\View', $id);
        $model->setPosition($position);
        $model->setColumn($column);

        try {
            Shopware()->Models()->persist($model);
            Shopware()->Models()->flush();
        } catch (\Doctrine\ORM\ORMException $e) {
            $this->View()->assign(array('success' => false, 'message' => $e->getMessage()));
            return;
        }

        $this->View()->assign(array('success' => true, 'newPosition' => $position, 'newColumn' => $column));
    }

    public function addWidgetViewAction()
    {
        $auth = Shopware()->Auth();

        if (!$auth->hasIdentity()) {
            $this->View()->assign(array('success' => false));
        }

        $identity = $auth->getIdentity();
        $userID = (int) $identity->id;

        $request = $this->Request();
        $widgetId = $request->getParam('id');
        $label = $request->getParam('label');
        $column = $request->getParam('column');
        $position = $request->getParam('position');

        try {
            $model = new \Shopware\Models\Widget\View();
            $model->setWidget(
                Shopware()->Models()->find('Shopware\Models\Widget\Widget', $widgetId)
            );
            $model->setAuth(
                Shopware()->Models()->find('Shopware\Models\User\User', $userID)
            );
            $model->setLabel($label);
            $model->setColumn($column);
            $model->setPosition($position);

            Shopware()->Models()->persist($model);
            Shopware()->Models()->flush();

        } catch (\Doctrine\ORM\ORMException $e) {
            $this->View()->assign(array('success' => false, 'message' => $e->getMessage()));
        }

        $viewId = $model->getId();

        $this->View()->assign(array('success' => !empty($viewId), 'viewId' => $viewId));
    }

    public function removeWidgetViewAction()
    {
        $auth = Shopware()->Auth();

        if (!$auth->hasIdentity()) {
            $this->View()->assign(array('success' => false));
        }

        $request = $this->Request();
        $views = $request->getParam('views');

        try {
            foreach($views as $view) {
                $model = Shopware()->Models()->find('Shopware\Models\Widget\View', $view['id']);
                Shopware()->Models()->remove($model);
            }

            Shopware()->Models()->flush();

        } catch (\Doctrine\ORM\ORMException $e) {
            $this->View()->assign(array('success' => false, 'message' => $e->getMessage()));
        }

        $this->View()->assign(array('success' => true, 'views' => $views));
    }


    /**
     * Gets the turnover and vistors amount for the
     * chart and the grid in the "Turnover - Yesterday and today"-widget.
     *
     * @public
     * @return void
     */
    public function getTurnOverVisitorsAction()
    {
        // Get turnovers
        $fetchAmount = Shopware()->Db()->fetchRow(" SELECT
        (SELECT sum(invoice_amount/currencyFactor) AS amount FROM s_order WHERE TO_DAYS(ordertime) = TO_DAYS(now()) AND status != 4 AND status != -1) AS today,
        (SELECT sum(invoice_amount/currencyFactor) AS amount FROM s_order WHERE TO_DAYS(ordertime) = (TO_DAYS( NOW( ) )-1)  AND status != 4 AND status != -1) AS yesterday
        ");

        if (empty($fetchAmount["today"])) $fetchAmount["today"] = 0.00;
        if (empty($fetchAmount["yesterday"])) $fetchAmount["yesterday"] = 0.00;

        $fetchAmount['today'] = round($fetchAmount['today'], 2);
        $fetchAmount['yesterday'] = round($fetchAmount['yesterday'], 2);
        // Get visitors
        $fetchVisitors = Shopware()->Db()->fetchRow("
        SELECT
        (SELECT SUM(uniquevisits) FROM s_statistics_visitors WHERE datum = CURDATE()) AS today,
        (SELECT SUM(uniquevisits) FROM s_statistics_visitors WHERE datum = DATE_SUB(CURDATE(),INTERVAL 1 DAY)) AS yesterday
        ");

        // Get new customers
        $fetchCustomers = Shopware()->Db()->fetchRow("
        SELECT
        (SELECT COUNT(DISTINCT id) FROM s_user WHERE TO_DAYS( firstlogin ) = TO_DAYS( NOW( ) ) ) AS today,
        (SELECT COUNT(DISTINCT id) FROM s_user WHERE firstlogin = DATE_SUB(CURDATE(),INTERVAL 1 DAY)) AS yesterday
        ");

        // Get order-count
        $fetchOrders = Shopware()->Db()->fetchRow("
        SELECT
        (SELECT COUNT(DISTINCT id) AS orders FROM s_order WHERE TO_DAYS( ordertime ) = TO_DAYS( NOW( ) ) AND status != 4 AND status != -1) AS today,
        (SELECT COUNT(DISTINCT id) AS orders FROM s_order WHERE TO_DAYS(ordertime) = (TO_DAYS( NOW( ) )-1) AND status != 4 AND status != -1) AS yesterday
        ");


        if (empty($timeBack)) {
            $timeBack = 7;
        }

        $sql = "
        SELECT
            COUNT(id) AS `countOrders`,
            DATE_FORMAT(DATE_SUB(now(),INTERVAL ? DAY),'%d.%m.%Y') AS point,
            ((SELECT SUM(uniquevisits) FROM s_statistics_visitors WHERE datum >= DATE_SUB(now(),INTERVAL ? DAY) GROUP BY DATE_SUB(now(),INTERVAL ? DAY))) AS visitors
        FROM `s_order`
        WHERE
            ordertime >= DATE_SUB(now(),INTERVAL ? DAY)
        AND
            status != 4
        AND
            status != -1
        GROUP BY
            DATE_SUB(now(),INTERVAL ? DAY)
        ";
        $fetchConversion = Shopware()->Db()->fetchRow($sql,array($timeBack,$timeBack,$timeBack,$timeBack,$timeBack));
        $fetchConversion = number_format($fetchConversion["countOrders"] /$fetchConversion["visitors"] * 100,2);

        $namespace = Shopware()->Snippets()->getNamespace('backend/widget/controller');
        $this->View()->assign(array(
            'success' => true,
            'data' => array(
                array('name' => $namespace->get('today', 'Today'), 'turnover' => $fetchAmount["today"], 'visitors' => $fetchVisitors["today"], 'newCustomers' => $fetchCustomers["today"], 'orders' => $fetchOrders["today"]),
                array('name' => $namespace->get('yesterday', 'Yesterday'), 'turnover' => $fetchAmount["yesterday"], 'visitors' => $fetchVisitors["yesterday"], 'newCustomers' => $fetchCustomers["yesterday"], 'orders' => $fetchOrders["yesterday"])
            ),
            'conversion' => $fetchConversion
        ));
    }

    /**
     * Gets the last visitors and customers for
     * the chart and the grid in the "Customers and visitors"-widget.
     *
     * @public
     * @return void
     */
    public function getVisitorsAction()
    {
        if (empty($timeBack)) {
            $timeBack = 8;
        }

        // Get visitors in defined time-range
        $sql = "
        SELECT datum AS `date`, SUM(uniquevisits) AS visitors
        FROM s_statistics_visitors
        WHERE datum >= DATE_SUB(now(),INTERVAL ? DAY)
        GROUP BY datum
        ";

        $data = Shopware()->Db()->fetchAll($sql,array($timeBack));

        $result[] = array();
        foreach ($data as $row) {
            $result[] = array(
                "timestamp" => strtotime($row["date"]),
                "date" => date('d.m.Y', strtotime($row["date"])),
                "visitors" => $row["visitors"]
            );
        }

        // Get current users online
        $currentUsers = Shopware()->Db()->fetchOne("SELECT COUNT(DISTINCT remoteaddr) FROM s_statistics_currentusers WHERE time > DATE_SUB(NOW(), INTERVAL 3 MINUTE)");
        if (empty($currentUsers)) $currentUsers = 0;

        // Get current users logged in
        $fetchLoggedInUsers = Shopware()->Db()->fetchAll("
        SELECT s.userID,
        (SELECT SUM(quantity * price) AS amount FROM s_order_basket WHERE userID = s.userID GROUP BY sessionID ORDER BY id DESC LIMIT 1) AS amount,
        (SELECT IF(ub.company,ub.company,CONCAT(ub.firstname,' ',ub.lastname)) FROM s_user_billingaddress AS ub WHERE ub.userID = s.userID) AS customer
        FROM s_statistics_currentusers s
        WHERE userID != 0
        GROUP BY remoteaddr
        ORDER BY amount DESC
        LIMIT 6
        ");

        foreach ($fetchLoggedInUsers as &$user) {
            $user["customer"] = htmlentities($user["customer"], null,"UTF-8");
        }

        $this->View()->assign(array(
            'success' => true,
            'data' => array(
                'customers' => $fetchLoggedInUsers,
                'visitors' => $result,
                'currentUsers' => $currentUsers
            )
        ));
    }

    /**
     * Gets the latest orders for the "last orders" widget.
     *
     * @public
     * @return void
     */
    public function getLastOrdersAction()
    {
        $addSqlPayment = ""; $addSqlSubshop = "";
        if (!empty($subshopID)) {
            $addSqlSubshop = "
            AND s_order.subshopID = ".Shopware()->Db()->quote($subshopID);
        }

        if (!empty($restrictPayment)) {
            $addSqlPayment = "
            AND s_order.paymentID = ".Shopware()->Db()->quote($restrictPayment);
        }

        $sql = "
        SELECT s_order.id AS id, currency,currencyFactor,firstname,lastname, company, subshopID, paymentID,  ordernumber AS orderNumber, transactionID, s_order.userID AS customerId, invoice_amount,invoice_shipping, ordertime AS `date`, status, cleared
        FROM s_order
        LEFT JOIN s_order_billingaddress ON s_order_billingaddress.userID = s_order.userID
        WHERE
            s_order.status != -1
        $addSqlSubshop
        $addSqlPayment
        AND
            ordertime >= DATE_SUB(now(),INTERVAL 14 DAY)
        GROUP BY s_order.id
        ORDER BY ordertime DESC
        LIMIT 20
        ";

        $result = Shopware()->Db()->fetchAll($sql);
        foreach ($result as &$order) {
            $order["customer"] = htmlentities($order["company"] ? $order["company"] : $order["firstname"]." ".$order["lastname"],ENT_QUOTES,"UTF-8");
            $amount = round(($order["invoice_amount"]/$order["currencyFactor"]),2);
            $order["amount"] = $amount;
            if (strlen($order["customer"])>25) {
                $order["customer"] = substr($order["customer"],0,25)."..";
            }
            unset($order["firstname"]); unset($order["lastname"]);
        }

        $this->View()->assign(array(
            'success' => true,
            'data' => $result
        ));
    }

    /**
     * Gets the saved notice from the database and
     * assigns it to the view-
     *
     * @public
     * @return void
     */
    public function getNoticeAction()
    {
        $userID = $_SESSION["Shopware"]["Auth"]->id;

        $noticeMsg = Shopware()->Db()->fetchOne("
        SELECT notes FROM s_plugin_widgets_notes WHERE userID = ?
        ",array($userID));

        $this->View()->assign(array('success' => true, 'notice' => $noticeMsg));
    }

    /**
     * Saves the notice text from the notice widget.
     *
     * @public
     * @return void
     */
    public function saveNoticeAction()
    {
        $noticeMsg = (string) $this->Request()->getParam('notice');

        $userID = $_SESSION["Shopware"]["Auth"]->id;

        if (empty($userID)) {
            $this->View()->assign(array('success' => false, 'message' => 'No user id'));
            return;
        }
        if (Shopware()->Db()->fetchOne("SELECT id FROM s_plugin_widgets_notes WHERE userID = ?",array($userID))) {
            // Update
            Shopware()->Db()->query("
            UPDATE s_plugin_widgets_notes SET notes = ? WHERE userID = ?
            ",array($noticeMsg,$userID));
        } else {
            // Insert
            Shopware()->Db()->query("
            INSERT INTO s_plugin_widgets_notes (userID, notes)
            VALUES (?,?)
            ",array($userID,$noticeMsg));
        }
        $this->View()->assign(array('success' => true, 'message' => 'Successfully saved.'));
    }

    /**
     * Gets the last registered merchant for the "merchant unlock"-widget.
     *
     * @public
     * @return void
     */
    public function getLastMerchantAction()
    {
        // Fetch all users that needs to get unlocked
        $sql = "SELECT DISTINCT s_user.active AS active, customergroup,validation,email,s_core_customergroups.description AS customergroup_name, validation AS customergroup_id, s_user.id AS id, lastlogin AS date, company AS company_name, customernumber, CONCAT(firstname,' ',lastname) AS customer
        FROM s_user LEFT JOIN s_core_customergroups
        ON groupkey = validation,
        s_user_billingaddress
        WHERE
        s_user.id = s_user_billingaddress.userID
        AND validation != '' AND validation != '0'
        ORDER BY s_user.firstlogin DESC";
        $fetchUsersToUnlock = Shopware()->Db()->fetchAll($sql);

        foreach ($fetchUsersToUnlock as &$user) {
            $user["customergroup_name"] = htmlentities($user["customergroup_name"],null,"UTF-8");
            $user["company_name"] = htmlentities($user["company_name"],null,"UTF-8");
            $user["customer"] = htmlentities($user["customer"],null,"UTF-8");
        }


        $this->View()->assign(array('success' => true, 'data' => $fetchUsersToUnlock));
    }

    /**
     * Creates the deny or allow mail from the db and assigns it to
     * the view.
     *
     * @public
     * @return bool
     */
    public function requestMerchantFormAction()
    {
        $customergroup = (string) $this->Request()->getParam('customerGroup');
        $userId = (int) $this->Request()->getParam('id');
        $mode = (string) $this->Request()->getParam('mode');

        if ($mode === 'allow') {
            $tplMail = 'sCUSTOMERGROUP%sACCEPTED';
        } else {
            $tplMail = 'sCUSTOMERGROUP%sREJECTED';
        }
        $tplMail = sprintf($tplMail, $customergroup);

        $builder = Shopware()->Models()->createQueryBuilder();
        $builder->select(array('mail'))
            ->from('Shopware\Models\Mail\Mail', 'mail')
            ->where('mail.name = ?1')
            ->setParameter(1, $tplMail);

        $mail = $builder->getQuery()->getOneOrNullResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);
        if (empty($mail)) {
            $this->View()->assign(array('success' => false, 'message' => 'There is no mail for the specific customer group'));
            return false;
        }

        $builder = Shopware()->Models()->createQueryBuilder();
        $builder->select(array('customer.email'))
            ->from('Shopware\Models\Customer\Customer', 'customer')
            ->where('customer.id = ?1')
            ->setParameter(1, $userId);

        $email = $builder->getQuery()->getOneOrNullResult(\Doctrine\ORM\AbstractQuery::HYDRATE_ARRAY);
        if (empty($email)) {
            $this->View()->assign(array('success' => false, 'message' => 'There is no user for the specific user id'));
            return false;
        }

        $mail['toMail'] = $email['email'];
        $mail['content'] = nl2br($mail['content']);
        $mail['userId'] = $userId;
        $mail['status'] = ($mode === 'allow' ? 'accepted' : 'rejected');
        $this->View()->assign(array('success' => true, 'data' => $mail));
    }

    /**
     * Sends the mail to the merchant if the inquiry was
     * sucessful or was declined.
     *
     * @public
     * @return bool
     */
    public function sendMailToMerchantAction()
    {
        $params = $this->Request()->getParams();
        $mail = clone Shopware()->Mail();

        $toMail = $params['toMail'];
        $fromName = $params['fromName'];
        $fromMail = $params['fromMail'];
        $subject = $params['subject'];
        $content = $params['content'];
        $userId = $params["userId"];
        $status = $params["status"];

        if (!$toMail || !$fromName || !$fromMail || !$subject || !$content || !$userId) {
            $this->View()->assign(array('success' => false, 'message' => 'All required fiels needs to be filled.'));
            return false;
        }

        $content = preg_replace('`<br(?: /)?>([\\n\\r])`', '$1', $params['content']);

        $compiler = new Shopware_Components_StringCompiler($this->View());
        $defaultContext = array(
            'sConfig'  => Shopware()->Config(),
        );
        $compiler->setContext($defaultContext);

        // Send eMail to customer
        $mail->IsHTML(false);
        $mail->From     = $compiler->compileString($fromMail);
        $mail->FromName = $compiler->compileString($fromName);
        $mail->Subject  = $compiler->compileString($subject);
        $mail->Body     = $compiler->compileString($content);
        $mail->ClearAddresses();
        $mail->AddAddress($toMail, "");

        if (!$mail->Send()) {
            $this->View()->assign(array('success' => false, 'message' => 'The mail could not be sent.'));
            return false;
        } else {
            if ($status == "accepted") {
                Shopware()->Db()->query("
                UPDATE s_user SET customergroup = validation, validation = '' WHERE id = ?
                ",array($userId));
            } else {
                Shopware()->Db()->query("
                UPDATE s_user SET validation = '' WHERE id = ?
                ",array($userId));
            }
        }
        $this->View()->assign(array('success' => true, 'message' => 'The mail was send successfully.'));
    }
}