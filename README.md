# Forum App

## Description
Welcome to my Forum App. This is an online forum where users can ask questions and provide feedback to others’ questions as well. React library has been used for the client side development and PHP Symfony framework is used for the backend development.

## Supported features
* User - Login | Logout | Register
* Posts - List | Search | Add | Approve | Reject | Delete
* Comments - Add

## Application stack
The application is divided in two parts
* Backend
* Frontend

## Backend stack
* PHP 8.1
* Symfony 6.1
* Doctrine ORM 2.13

Backend services are separated from the frontend. Use REST API to access backend functionalities.

## Install and Run the Project
1. Install Apache web server
2. Install MySQL server
3. Install PHP with Composer
4. Clone the repository - git clone https://github.com/dlakmalb/forum_server_side.git
5. Go to project directory - cd forum_server_side
6. Run composer install
7. Run migrations - php bin/console doctrine:migrations:migrate
8. Change server side .env file by providing host name, password and database name
9. Start backend server - symfony server:start --port=8000
10. Forum app will be available at localhost://8000


