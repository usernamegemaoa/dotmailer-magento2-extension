<?php

namespace Dotdigitalgroup\Email\Model\Trial;

use Dotdigitalgroup\Email\Helper\Config;
use Dotdigitalgroup\Email\Model\Apiconnector\Client;

class TrialSetup
{
    /**
     * @var \Dotdigitalgroup\Email\Helper\Data
     */
    public $helper;
    /**
     * @var \Dotdigitalgroup\Email\Model\Connector\Datafield
     */
    public $dataField;
    /**
     * @var \Magento\Framework\App\Config\ReinitableConfigInterface
     */
    public $config;

    /**
     * TrialSetup constructor.
     *
     * @param \Dotdigitalgroup\Email\Helper\Data $helper
     * @param \Dotdigitalgroup\Email\Model\Connector\Datafield $dataField
     * @param \Magento\Framework\App\Config\ReinitableConfigInterface $config
     */
    public function __construct(
        \Dotdigitalgroup\Email\Helper\Data $helper,
        \Dotdigitalgroup\Email\Model\Connector\Datafield $dataField,
        \Magento\Framework\App\Config\ReinitableConfigInterface $config
    ) {
    
        $this->helper = $helper;
        $this->dataField = $dataField;
        $this->config = $config;
    }

    /**
     * Save api credentioals.
     *
     * @param $apiUser
     * @param $apiPass
     *
     * @return bool
     */
    public function saveApiCreds($apiUser, $apiPass)
    {
        $this->helper->saveConfigData(
            Config::XML_PATH_CONNECTOR_API_ENABLED,
            '1',
            'default',
            0
        );
        $this->helper->saveConfigData(
            Config::XML_PATH_CONNECTOR_API_USERNAME,
            $apiUser,
            'default',
            0
        );
        $this->helper->saveConfigData(
            Config::XML_PATH_CONNECTOR_API_PASSWORD,
            $apiPass,
            'default',
            0
        );

        //Clear config cache
        $this->config->reinit();

        return true;
    }

    /**
     * Setup data fields.
     *
     * @param $username
     * @param $password
     *
     * @return bool
     */
    public function setupDataFields($username, $password)
    {
        $error = false;
        $apiModel = false;
        if ($this->helper->isEnabled()) {
            $apiModel = $this->helper->getWebsiteApiClient(0, $username, $password);
        }
        if (!$apiModel) {
            $error = true;
            $this->helper->log('setupDataFields client is not enabled');
        } else {
            $dataFields = $this->dataField->getContactDatafields();
            foreach ($dataFields as $key => $dataField) {
                $response = $apiModel->postDataFields($dataField);
                //ignore existing datafields message
                if (isset($response->message) &&
                    $response->message != Client::API_ERROR_DATAFIELD_EXISTS
                ) {
                    $error = true;
                } else {
                    //map the successfully created data field
                    $this->helper->saveConfigData(
                        'connector_data_mapping/customer_data/' . $key,
                        strtoupper($dataField['name']),
                        'default',
                        0
                    );
                    $this->helper->log('successfully connected : ' . $dataField['name']);
                }
            }
        }

        return $error == true ? false : true;
    }

    /**
     * Create certain address books.
     *
     * @param $username
     * @param $password
     *
     * @return bool
     */
    public function createAddressBooks($username, $password)
    {
        $addressBooks = [
            ['name' => 'Magento_Customers', 'visibility' => 'Private'],
            ['name' => 'Magento_Subscribers', 'visibility' => 'Private'],
            ['name' => 'Magento_Guests', 'visibility' => 'Private'],
        ];
        $error = false;
        $client = false;
        if ($this->helper->isEnabled()) {
            $client = $this->helper->getWebsiteApiClient(0, $username, $password);
        }
        if (!$client) {
            $error = true;
            $this->helper->log('createAddressBooks client is not enabled');
        } else {
            foreach ($addressBooks as $addressBook) {
                $addressBookName = $addressBook['name'];
                $visibility = $addressBook['visibility'];
                if (!empty($addressBookName)) {
                    $response = $client->postAddressBooks($addressBookName, $visibility);
                    if (isset($response->message) &&
                        $response->message != Client::API_ERROR_ADDRESSBOOK_DUPLICATE
                    ) {
                        $error = true;
                    } elseif (isset($response->id)) {
                        $this->mapAddressBook($addressBookName, $response->id);
                    } else { //Need to fetch addressbook id to map. Addressbook already exist.
                        $response = $client->getAddressBooks();
                        if (!isset($response->message)) {
                            foreach ($response as $book) {
                                if ($book->name == $addressBookName) {
                                    $this->mapAddressBook($addressBookName, $book->id);
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $error == true ? false : true;
    }

    /**
     * Map the successfully created address book
     *
     * @param $name
     * @param $id
     */
    public function mapAddressBook($name, $id)
    {
        $addressBookMap = [
            'Magento_Customers' => Config::XML_PATH_CONNECTOR_CUSTOMERS_ADDRESS_BOOK_ID,
            'Magento_Subscribers' => Config::XML_PATH_CONNECTOR_SUBSCRIBERS_ADDRESS_BOOK_ID,
            'Magento_Guests' => Config::XML_PATH_CONNECTOR_GUEST_ADDRESS_BOOK_ID,
        ];

        $this->helper->saveConfigData($addressBookMap[$name], $id, 'default', 0);
        $this->helper->log('successfully connected address book : ' . $name);
    }

    /**
     * Enable certain syncs for newly created trial account.
     *
     * @return bool
     */
    public function enableSyncForTrial()
    {
        $this->helper->saveConfigData(
            Config::XML_PATH_CONNECTOR_SYNC_CUSTOMER_ENABLED,
            '1',
            'default',
            0
        );
        $this->helper->saveConfigData(
            Config::XML_PATH_CONNECTOR_SYNC_GUEST_ENABLED,
            '1',
            'default',
            0
        );
        $this->helper->saveConfigData(
            Config::XML_PATH_CONNECTOR_SYNC_SUBSCRIBER_ENABLED,
            '1',
            'default',
            0
        );
        $this->helper->saveConfigData(
            Config::XML_PATH_CONNECTOR_SYNC_ORDER_ENABLED,
            '1',
            'default',
            0
        );

        return true;
    }

    /**
     * Save api endpoint.
     *
     * @param $value
     */
    public function saveApiEndPoint($value)
    {
        $this->helper->saveConfigData(
            Config::PATH_FOR_API_ENDPOINT,
            $value,
            'default',
            0
        );
    }
}
