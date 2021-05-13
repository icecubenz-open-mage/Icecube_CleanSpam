<?php

require_once 'abstract.php';

class Icecube_Shell_CleanSpam extends Mage_Shell_Abstract
{
    /**
     * Run the command
     *
     * @return Icecube_Shell_CleanSpam
     */
    public function run()
    {
        if ($this->getArg('customers')) {
            $this->_cleanSpamCustomers();
        } elseif ($this->getArg('subscribers')) {
            $this->_cleanSpamSubscribers();
        }

        // if nothing called, just do the help
        echo $this->usageHelp();

        return $this;
    }

    protected function _cleanSpamCustomers()
    {
        $deletedCustomers = 0;

        $customers = Mage::getModel('customer/customer')
            ->getCollection()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter(
                array(
                    // array('attribute' => 'email', 'like' => '%.ru'),
                    // array('attribute' => 'email', 'like' => '%.qq.com'),

                    // 40 or mor of any character for either customer name
                    array('attribute' => 'lastname',  'regexp' => '.{40,}'),
                    array('attribute' => 'firstname', 'regexp' => '.{40,}'),
                )
            );

        echo "{$customers->getSize()} customers found\r\n";

        foreach ($customers as $customer) {
            // skip if address is set
            if ($customer->getAddresses()) {
                continue;
            }

            // skip if any orders
            $customerOrder = Mage::getResourceModel('sales/order_collection')
                ->addFieldToSelect('entity_id')
                ->addFieldToFilter('customer_id', $customer->getId())
                ->setCurPage(1)
                ->setPageSize(1)
                ->getFirstItem();
            if ($customerOrder->getId()) {
                continue;
            }

            try {
                if ((bool) $this->getArg('no-dry-run')) {
                    echo "DELETING: ({$customer->getId()}) {$customer->getEmail()}: {$customer->getFirstname()} {$customer->getLastname()}\r\n";
                    Mage::log("DELETING: ({$customer->getId()}) {$customer->getEmail()}: {$customer->getFirstname()} {$customer->getLastname()}", null, 'clean-spam.log');
                    Mage::getModel('customer/customer')->load($customer->getId())->delete();
                    $deletedCustomers++;
                } else {
                    echo "DRY RUN - NOT DELETING: ({$customer->getId()}) {$customer->getEmail()}: {$customer->getFirstname()} {$customer->getLastname()}\r\n";
                    $deletedCustomers++;
                }
            } catch (Exception $e) {
                Mage::log($e->getMessage(), null, 'clean-spam.log');
            }
        }

        echo "$deletedCustomers customers deleted\r\n";

        return $this;
    }

    protected function _cleanSpamSubscribers()
    {
        // Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED   = 1;
        // Mage_Newsletter_Model_Subscriber::STATUS_NOT_ACTIVE   = 2;
        // Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED = 3;
        // Mage_Newsletter_Model_Subscriber::STATUS_UNCONFIRMED  = 4;

        $deletedSubscribers= 0;

        $newsletterSubscribers = Mage::getResourceModel('newsletter/subscriber_collection')
            ->addFieldToFilter('subscriber_status', array ('neq' => Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED));

        echo "{$newsletterSubscribers->getSize()} subscribers found\r\n";

        foreach ($newsletterSubscribers as $subscriber) {
            try {
                if ((bool) $this->getArg('no-dry-run')) {
                    echo "DELETING: ({$subscriber->getId()}) {$subscriber->getEmail()}\r\n";
                    Mage::log("DELETING: ({$subscriber->getId()}) {$subscriber->getEmail()}", null, 'clean-spam.log');
                    $subscriber->delete();
                    $deletedSubscribers++;
                } else {
                    echo "DRY RUN - NOT DELETING: ({$subscriber->getId()}) {$subscriber->getEmail()}\r\n";
                    $deletedSubscribers++;
                }
            } catch (Exception $e) {
                Mage::log($e->getMessage(), null, 'clean-spam.log');
            }
        }

        echo "$deletedSubscribers subscribers deleted\r\n";

        return $this;
    }


    /**
     * Retrieve Usage Help Message
     *
     */
    public function usageHelp()
    {
        return <<<USAGE
Usage:  php -f clean_spam.php -- [options]

  customers          Work on Customers
  subscribers       Work on Subscribers
  --no-dry-run      Actually clean up the spam
  help              This help

USAGE;
    }
}

// run the shell script
$shell = new Icecube_Shell_CleanSpam();
$shell->run();
