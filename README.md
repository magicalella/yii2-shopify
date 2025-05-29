# yii2-shopify
 Modulo per integrare la  Yii con Shopify
 
 [Shopify Technical API Documentation](https://shopify.dev/docs/api/admin-graphql)
 Generate access Token (https://shopify.dev/docs/apps/build/authentication-authorization/access-tokens/generate-app-access-tokens-admin)
 
 Installation
 ------------
 
 The preferred way to install this extension is through [composer](http://getcomposer.org/download/).
 
 Run
 
 ```
 composer require "magicalella/yii2-shopify" "*"
 ```
 
 or add
 
 ```
 "magicalella/yii2-shopify": "*"
 ```
 
 to the require section of your `composer.json` file.
 
Configuration
-------------
 **Component Setup**
 1. Add component to your config file
 ```php
 'components' => [
     // ...
     'shopify' => [
         'class' => 'magicalella\shopify\Shopify',
         'storeName' => 'store name in Shopify',
         'accessToken' => 'accessToken generate in Shopify APP',
         'apiVersion' => 'api version settin durinf build APP in Shopify',
     ],
 ]
 ```

Usage:
--------- 
**Query:**
 
 ```php
 const QUERY_CHECK = <<<QUERY
 query test (\$userId: Int!){
   userInfo (userId: \$userId) {
     firstname
     lastname
     email
   }
 }
 QUERY;
 
 $result = Yii::$app->graphql->execute(QUERY_CHECK, ['userId' => (int) $userId], 'github');
 ```
 
 
 
 **ActiveDataProvider:**
 
 ```php
 use magicalella\shopify\ShopifyDataProvider;
 
 // If you want to use pagination in ActiveDataProvider, Set $offset and $limit in your query. Everything will be handled automatically.
 const QUERY = <<<QUERY
 query(\$limit: Int, \$offset: Int){
   categories (first: \$limit, skip: \$offset){
     id
     name
     icon
   }
 }
 QUERY;
 
 $dataProvider = new ShopifyDataProvider([
     'query' => QUERY,
     'queryCallback' => 'data.categories', // How to access the array in responded query result? More: https://www.yiiframework.com/doc/guide/2.0/en/helper-array#getting-values
     'totalCountQuery' => 'query { categoriesConnection { aggregate { count } } }',
     'target' => 'prisma',
 ]);
 
 return $this->render('index', [
     'dataProvider' => $dataProvider,
 ]);
 ```
