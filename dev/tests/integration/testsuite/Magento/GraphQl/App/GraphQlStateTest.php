<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\App;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Registry;
use Magento\GraphQl\Quote\GetMaskedQuoteIdByReservedOrderId;

/**
 * Tests the dispatch method in the GraphQl Controller class using a simple product query
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 * @magentoAppArea graphql
 * @magentoDataFixture Magento/Catalog/_files/multiple_mixed_products.php
 * @magentoDataFixture Magento/Catalog/_files/categories.php
 *
 */
class GraphQlStateTest extends \PHPUnit\Framework\TestCase
{


    /** @var CustomerRepositoryInterface */
    private CustomerRepositoryInterface $customerRepository;

    /** @var Registry */
    private $registry;

    /**
     * @var GetMaskedQuoteIdByReservedOrderId
     */
    private $getMaskedQuoteIdByReservedOrderId;

    private $graphQlStateDiff;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->graphQlStateDiff = new GraphQlStateDiff();
//        $this->getMaskedQuoteIdByReservedOrderId =
//            $this->graphQlStateDiff->getTestObjectManager()->get(GetMaskedQuoteIdByReservedOrderId::class);
        parent::setUp();
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->graphQlStateDiff->tearDown();
        parent::tearDown();
    }

    /**
     * @magentoDataFixture Magento/Customer/_files/customer.php
     * @magentoDataFixture Magento/Customer/_files/customer_address.php
     * @dataProvider customerDataProvider
     * @return void
     * @throws \Exception
     */
    public function testCustomerState(
        string $query,
        array $variables,
        array $variables2,
        array $authInfo,
        string $operationName,
        string $expected,
    ) : void {
        if ($operationName === 'createCustomer') {
            $this->customerRepository = $this->graphQlStateDiff->getTestObjectManager()
                ->get(CustomerRepositoryInterface::class);
            $this->registry = $this->graphQlStateDiff->getTestObjectManager()->get(Registry::class);
            $this->registry->register('isSecureArea', true);
            try {
                $customer = $this->customerRepository->get($variables['email']);
                $this->customerRepository->delete($customer);
                $customer2 = $this->customerRepository->get($variables2['email']);
                $this->customerRepository->delete($customer2);
            } catch (\Exception $e) {
                // Customer does not exist
            } finally {
                $this->registry->unregister('isSecureArea', false);
            }
        }
        $this->graphQlStateDiff->
            testState($query, $variables, $variables2, $authInfo, $operationName, $expected, $this);
    }

    /**
     * Runs various GraphQL queries and checks if state of shared objects in Object Manager have changed
     * @magentoDataFixture Magento/Store/_files/store.php
     * @magentoConfigFixture default_store catalog/seo/product_url_suffix test_product_suffix
     * @magentoConfigFixture default_store catalog/seo/category_url_suffix test_category_suffix
     * @magentoConfigFixture default_store catalog/seo/title_separator ___
     * @magentoConfigFixture default_store catalog/frontend/list_mode 2
     * @magentoConfigFixture default_store catalog/frontend/grid_per_page_values 16
     * @magentoConfigFixture default_store catalog/frontend/list_per_page_values 8
     * @magentoConfigFixture default_store catalog/frontend/grid_per_page 16
     * @magentoConfigFixture default_store catalog/frontend/list_per_page 8
     * @magentoConfigFixture default_store catalog/frontend/default_sort_by asc
     * @magentoDataFixture Magento/GraphQl/Catalog/_files/simple_product.php
     * @magentoDataFixture Magento/GraphQl/Quote/_files/guest/create_empty_cart.php
     * @magentoDataFixture Magento/GraphQl/Quote/_files/add_simple_product.php
     * @magentoDataFixture Magento/GraphQl/Quote/_files/set_new_shipping_address.php
     * @magentoDataFixture Magento/GraphQl/Quote/_files/set_new_billing_address.php
     * @dataProvider queryDataProvider
     * @param string $query
     * @param array $variables
     * @param array $variables2  This is the second set of variables to be used in the second request
     * @param array $authInfo
     * @param string $operationName
     * @param string $expected
     * @return void
     * @throws \Exception
     */
    public function testState(
        string $query,
        array $variables,
        array $variables2,
        array $authInfo,
        string $operationName,
        string $expected,
    ): void {
        if (array_key_exists(1, $authInfo)) {
            $authInfo1 = $authInfo[0];
            $authInfo2 = $authInfo[1];
        } else {
            $authInfo1 = $authInfo;
            $authInfo2 = $authInfo;
        }
        if ($operationName == 'getCart') {
            $variables['id'] = $this->getMaskedQuoteIdByReservedOrderId->execute($variables['id']);
        }
        $jsonEncodedRequest = json_encode([
            'query' => $query,
            'variables' => $variables,
            'operationName' => $operationName
        ]);
        $output1 = $this->request($jsonEncodedRequest, $operationName, $authInfo1, true);
        $this->assertStringContainsString($expected, $output1);
        if ($variables2) {
            $jsonEncodedRequest = json_encode([
                'query' => $query,
                'variables' => $variables2,
                'operationName' => $operationName
            ]);
        }
        $output2 = $this->request($jsonEncodedRequest, $operationName, $authInfo2);
        $this->assertStringContainsString($expected, $output2);
    }

    /**
     * Queries, variables, operation names, and expected responses for test
     *
     * @return array[]
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function queryDataProvider(): array
    {
        return [
            'Get Navigation Menu by category_id' => [
                <<<'QUERY'
                query navigationMenu($id: Int!) {
                    category(id: $id) {
                        id
                        name
                        product_count
                        path
                        children {
                            id
                            name
                            position
                            level
                            url_key
                            url_path
                            product_count
                            children_count
                            path
                            productImagePreview: products(pageSize: 1) {
                                items {
                                    small_image {
                                        label
                                        url
                                    }
                                }
                            }
                        }
                    }
                }
                QUERY,
                ['id' => 4],
                [],
                [],
                'navigationMenu',
                '"id":4,"name":"Category 1.1","product_count":2,'
            ],
            'Get Product Search by product_name' => [
                <<<'QUERY'
                query productDetailByName($name: String, $onServer: Boolean!) {
                    products(filter: { name: { match: $name } }) {
                        items {
                            id
                            sku
                            name
                            ... on ConfigurableProduct {
                                configurable_options {
                                    attribute_code
                                    attribute_id
                                    id
                                    label
                                    values {
                                        default_label
                                        label
                                        store_label
                                        use_default_value
                                        value_index
                                    }
                                }
                                variants {
                                    product {
                                        #fashion_color
                                        #fashion_size
                                        id
                                        media_gallery_entries {
                                            disabled
                                            file
                                            label
                                            position
                                        }
                                        sku
                                        stock_status
                                    }
                                }
                            }
                            meta_title @include(if: $onServer)
                            meta_keyword @include(if: $onServer)
                            meta_description @include(if: $onServer)
                        }
                    }
                }
                QUERY,
                ['name' => 'Configurable%20Product', 'onServer' => false],
                [],
                [],
                'productDetailByName',
                '"sku":"configurable","name":"Configurable Product"'
            ],
            'Get List of Products by category_id' => [
                <<<'QUERY'
                query category($id: Int!, $currentPage: Int, $pageSize: Int) {
                    category(id: $id) {
                        product_count
                        description
                        url_key
                        name
                        id
                        breadcrumbs {
                            category_name
                            category_url_key
                            __typename
                        }
                        products(pageSize: $pageSize, currentPage: $currentPage) {
                            total_count
                            items {
                                id
                                name
                                # small_image
                                # short_description
                                url_key
                                special_price
                                special_from_date
                                special_to_date
                                price {
                                    regularPrice {
                                        amount {
                                            value
                                            currency
                                            __typename
                                        }
                                        __typename
                                    }
                                    __typename
                                }
                                __typename
                            }
                            __typename
                        }
                    __typename
                    }
                }
                QUERY,
                ['id' => 4, 'currentPage' => 1, 'pageSize' => 12],
                [],
                [],
                'category',
                '"url_key":"category-1-1","name":"Category 1.1"'
            ],
            'Get Simple Product Details by name' => [
                <<<'QUERY'
                query productDetail($name: String, $onServer: Boolean!) {
                    productDetail: products(filter: { name: { match: $name } }) {
                        items {
                            sku
                            name
                            price {
                                regularPrice {
                                    amount {
                                        currency
                                        value
                                    }
                                }
                            }
                            description {html}
                            media_gallery_entries {
                                label
                                position
                                disabled
                                file
                            }
                            ... on ConfigurableProduct {
                                configurable_options {
                                    attribute_code
                                    attribute_id
                                    id
                                    label
                                    values {
                                        default_label
                                        label
                                        store_label
                                        use_default_value
                                        value_index
                                    }
                                }
                                variants {
                                    product {
                                        id
                                        media_gallery_entries {
                                            disabled
                                            file
                                            label
                                            position
                                        }
                                        sku
                                        stock_status
                                    }
                                }
                            }
                            meta_title @include(if: $onServer)
                            # Yes, Products have `meta_keyword` and
                            # everything else has `meta_keywords`.
                            meta_keyword @include(if: $onServer)
                            meta_description @include(if: $onServer)
                        }
                    }
                }
                QUERY,
                ['name' => 'Simple Product1', 'onServer' => false],
                [],
                [],
                'productDetail',
                '"sku":"simple1","name":"Simple Product1"'
            ],
            'Get Url Info by url_key' => [
                <<<'QUERY'
                query resolveUrl($urlKey: String!) {
                    urlResolver(url: $urlKey) {
                        type
                        id
                    }
                }
                QUERY,
                ['urlKey' => 'no-route'],
                [],
                [],
                'resolveUrl',
                '"type":"CMS_PAGE","id":1'
            ],
            'Get available Stores' => [
                <<<'QUERY'
                query availableStores($currentGroup: Boolean!) {
                    availableStores(useCurrentGroup:$currentGroup) {
                        id,
                        code,
                        store_code,
                        store_name,
                        store_sort_order,
                        is_default_store,
                        store_group_code,
                        store_group_name,
                        is_default_store_group,
                        website_id,
                        website_code,
                        website_name,
                        locale,
                        base_currency_code,
                        default_display_currency_code,
                        timezone,
                        weight_unit,
                        base_url,
                        base_link_url,
                        base_media_url,
                        secure_base_url,
                        secure_base_link_url,
                        secure_base_static_url,
                        secure_base_media_url,
                        store_name
                        use_store_in_url
                    }
                }
                QUERY,
                ['currentGroup' => true],
                [],
                [],
                'availableStores',
                '"store_code":"default"'
            ],
            'Get store config' => [
                <<<'QUERY'
                query {
                    storeConfig {
                        product_url_suffix,
                        category_url_suffix,
                        title_separator,
                        list_mode,
                        grid_per_page_values,
                        list_per_page_values,
                        grid_per_page,
                        list_per_page,
                        catalog_default_sort_by,
                        root_category_id
                        root_category_uid
                    }
                }
                QUERY,
                [],
                [],
                [],
                'storeConfig',
                '"storeConfig":{"product_url_suffix":"test_product_suffix"'
            ],
            'Get Categories by name' => [
                <<<'QUERY'
                query categories($name: String!) {
                    categories(filters: {name: {match: $name}}
                            pageSize: 3
                            currentPage: 3
                          ) {
                            total_count
                            page_info {
                              current_page
                              page_size
                              total_pages

                          }
                        items {
                          name
                        }
                    }
                }
                QUERY,
                ['name' => 'Category'],
                [],
                [],
                'categories',
                '"data":{"categories"'
            ],
            'Get Products by name' => [
                <<<'QUERY'
                query products($name: String!) {
                    products(
                        search: $name
                        filter: {}
                        pageSize: 1000
                        currentPage: 1
                        sort: {name: ASC}
                      ) {

                    items {
                            name
                            image
                            {
                                url
                            }
                      attribute_set_id
                      canonical_url
                      color
                      country_of_manufacture
                      created_at
                      gift_message_available
                      id
                      manufacturer
                      meta_description
                      meta_keyword
                      meta_title
                      new_from_date
                      new_to_date
                      only_x_left_in_stock
                      options_container
                      rating_summary
                      review_count
                      sku
                      special_price
                      special_to_date
                      stock_status
                      swatch_image
                      uid
                      updated_at
                      url_key
                      url_path
                      url_suffix

                    }
                    page_info {
                      current_page
                      page_size
                      total_pages
                    }
                    sort_fields {
                      default
                    }
                    suggestions {
                      search
                    }
                    total_count
                  }
                }
                QUERY,
                ['name' => 'Simple Product1'],
                [],
                [],
                'products',
                '"data":{"products":{"items":[{'
            ],
            'Get Cart' => [
                <<<'QUERY'
                query cart($id: String!) {
                    cart(cart_id: $id) {
                        applied_coupons {
                          code
                        }

                        available_payment_methods {
                          code
                          is_deferred
                          title
                        }
                        billing_address {
                          city
                          company
                          firstname
                          lastname
                          postcode
                          street
                          telephone
                          uid
                          vat_id
                        }
                        email
                        id
                        is_virtual
                        items {
                          id
                          quantity
                          uid
                        }
                        selected_payment_method {
                          code
                          purchase_order_number
                          title
                        }
                        total_quantity
                    }
                }
                QUERY,
                ['id' => 'test_quote'],
                [],
                [],
                'getCart',
                '"cart":{"applied_coupons":null,"available_payment_methods":[{"code":"checkmo"'
            ],
        ];
    }

    /**
     * Queries, variables, operation names, and expected responses for test
     *
     * @return array[]
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function customerDataProvider(): array
    {
        return [
            'Create Customer' => [
                <<<'QUERY'
                mutation($firstname: String!, $lastname: String!, $email: String!, $password: String!) {
                 createCustomerV2(
                    input: {
                     firstname: $firstname,
                     lastname: $lastname,
                     email: $email,
                     password: $password
                     }
                ) {
                    customer {
                        created_at
                        prefix
                        firstname
                        middlename
                        lastname
                        suffix
                        email
                        default_billing
                        default_shipping
                        date_of_birth
                        taxvat
                        is_subscribed
                        gender
                        allow_remote_shopping_assistance
                    }
                }
            }
            QUERY,
                [
                    'firstname' => 'John',
                    'lastname' => 'Doe',
                    'email' => 'email1@example.com',
                    'password' => 'Password-1',
                ],
                [
                    'firstname' => 'John',
                    'lastname' => 'Doe',
                    'email' => 'email2@adobe.com',
                    'password' => 'Password-2',
                ],
                [],
                'createCustomer',
                '"email":"',
            ],
            'Update Customer' => [
                <<<'QUERY'
                    mutation($allow: Boolean!) {
                       updateCustomerV2(
                        input: {
                            allow_remote_shopping_assistance: $allow
                        }
                    ) {
                    customer {
                        allow_remote_shopping_assistance
                    }
                }
            }
            QUERY,
                ['allow' => true],
                ['allow' => false],
                ['email' => 'customer@example.com', 'password' => 'password'],
                'updateCustomer',
                'allow_remote_shopping_assistance'
            ],
            'Update Customer Address' => [
                <<<'QUERY'
                    mutation($addressId: Int!, $city: String!) {
                       updateCustomerAddress(id: $addressId, input: {
                        region: {
                            region: "Alberta"
                            region_id: 66
                            region_code: "AB"
                        }
                        country_code: CA
                        street: ["Line 1 Street","Line 2"]
                        company: "Company Name"
                        telephone: "123456789"
                        fax: "123123123"
                        postcode: "7777"
                        city: $city
                        firstname: "Adam"
                        lastname: "Phillis"
                        middlename: "A"
                        prefix: "Mr."
                        suffix: "Jr."
                        vat_id: "1"
                        default_shipping: true
                        default_billing: true
                      }) {
                        id
                        customer_id
                        region {
                          region
                          region_id
                          region_code
                        }
                        country_code
                        street
                        company
                        telephone
                        fax
                        postcode
                        city
                        firstname
                        lastname
                        middlename
                        prefix
                        suffix
                        vat_id
                        default_shipping
                        default_billing
                      }
                }
                QUERY,
                ['addressId' => 1, 'city' => 'New York'],
                ['addressId' => 1, 'city' => 'Austin'],
                ['email' => 'customer@example.com', 'password' => 'password'],
                'updateCustomerAddress',
                'city'
            ],
            'Update Customer Email' => [
                <<<'QUERY'
                    mutation($email: String!, $password: String!) {
                        updateCustomerEmail(
                        email: $email
                        password: $password
                    ) {
                    customer {
                        email
                    }
                  }
                }
                QUERY,
                ['email' => 'customer2@example.com', 'password' => 'password'],
                ['email' => 'customer@example.com', 'password' => 'password'],
                [
                    ['email' => 'customer@example.com', 'password' => 'password'],
                    ['email' => 'customer2@example.com', 'password' => 'password'],
                ],
                'updateCustomerEmail',
                'email',
            ],
            'Generate Customer Token' => [
                <<<'QUERY'
                    mutation($email: String!, $password: String!) {
                        generateCustomerToken(email: $email, password: $password) {
                            token
                        }
                    }
                QUERY,
                ['email' => 'customer@example.com', 'password' => 'password'],
                ['email' => 'customer@example.com', 'password' => 'password'],
                [],
                'generateCustomerToken',
                'token'
            ],
            'Get Customer' => [
                <<<'QUERY'
                    query {
                      customer {
                        created_at
                        date_of_birth
                        default_billing
                        default_shipping
                        date_of_birth
                        email
                        firstname
                        gender
                        id
                        is_subscribed
                        lastname
                        middlename
                        prefix
                        suffix
                        taxvat
                      }
                    }
                QUERY,
                [],
                [],
                ['email' => 'customer@example.com', 'password' => 'password'],
                'getCustomer',
                '"data":{"customer":{"created_at"'
            ],
        ];
    }
}
