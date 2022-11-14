<?php

namespace App\Controller;

use App\Entity\User;
use App\Utils\RepositoryTrait;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserController extends AbstractController
{
    use RepositoryTrait;

    /**
     * Check the login request.
     *
     * @param UserPasswordHasherInterface $passwordHasher
     * @return JsonResponse
     */
    public function checkLogin(
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $request = Request::createFromGlobals();
        $content = json_decode($request->getContent());
        $entityManager = $this->getEntityManager();

        // Validate content
        $this->validateLoginRequest($content);

        $entityManager->getConnection()->beginTransaction();

        try {
            $email = $content->email;
            $plainPassword = $content->password;

            $user = $this->getEntityRepository(User::class)
                ->findOneBy(['email' => $email]);

            /**
             * If logged user is a registered user then log into the system.
             */
            $result = 'success';
            $userId = $user ? $user->getId() : '';
            $isAdmin = $user ? $user->getIsAdmin() : false;
            $statusCode = 200;
            $message = 'Login successfully.';
            $isValidPassword = $user ?
                $passwordHasher->isPasswordValid($user, $plainPassword) :
                false;

            /**
             * If the provided email is not exist and
             * the password is invalid then throw an exception.
             */
            if (!$user || !$isValidPassword) {
                $result     = 'fail';
                $statusCode = 200;
                $message    = 'Bad credentials. Login failed!';
            }

            $entityManager->getConnection()->commit();
        } catch (Exception $error) {
            $entityManager->getConnection()->rollBack();
            throw $error;
        }

        return $this->json(
            [
                'result' => $result,
                'message' => $message,
                'user' => ['userId' => $userId, 'isAdmin' => $isAdmin]
            ],
            $statusCode,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Register a new user
     *
     * @return JsonResponse
     */
    public function registerUser(
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $request = Request::createFromGlobals();
        $content = json_decode($request->getContent());
        $entityManager = $this->getEntityManager();

        // Validate content
        $this->validateRegisterUserRequest($content);

        $entityManager->getConnection()->beginTransaction();

        try {
            $userId = '';
            $isAdmin = false;
            $email = $content->email;
            $plainPassword = $content->password;
            $user = $this->getEntityRepository(User::class)
                ->findOneBy(['email' => $email]);

            // If the provided email already exist then throw and exception.
            if ($user) {
                $result = 'fail';
                $statusCode = 200;
                $message = 'User already exist for the given e-mail.';
            } else {
                // Add new user to the database
                $newUser = new User();

                $newUser->setEmail($email);
                $newUser->setIsAdmin(false);

                $hashedPassword = $passwordHasher->hashPassword(
                    $newUser,
                    $plainPassword
                );

                $newUser->setPassword($hashedPassword);

                $entityManager->persist($newUser);
                $entityManager->flush();

                $userId = $newUser->getId();
                $isAdmin = $newUser->getIsAdmin();
                $result = 'success';
                $statusCode = 201;
                $message = 'New user created successfully.';
            }

            $entityManager->getConnection()->commit();
        } catch (Exception $error) {
            $entityManager->getConnection()->rollBack();
            throw $error;
        }

        return $this->json(
            [
                'result' => $result,
                'message' => $message,
                'user' => ['userId' => $userId, 'isAdmin' => $isAdmin]
            ],
            $statusCode,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Check the login request is valid or not
     *
     * @param object $content
     * @return void
     */
    private function validateLoginRequest(object $content): void
    {
        $emailRegex    = '/^\w+([.-]?\w+)*@\w+([.-]?\w+)*(\.\w{2,})+$/';
        $passwordRegex = '/(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9]).{5,}/';

        if (
            !property_exists($content, 'email') ||
            !$content->email ||
            !(preg_match($emailRegex, $content->email))
        )
            throw new Exception('E-mail is missing or invalid!', 500);

        if (
            !property_exists($content, 'password') ||
            empty($content->password) ||
            !(preg_match($passwordRegex, $content->password))
        ) {
            throw new Exception('Password is missing or invalid!', 500);
        }
    }

    /**
     * Check the register user request is valid or not
     *
     * @param object $content
     * @return void
     */
    private function validateRegisterUserRequest(object $content): void
    {
        $passwordRegex = '/(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9]).{5,}/';

        $this->validateLoginRequest($content);

        if (
            !property_exists($content, 'confirmPassword') ||
            empty($content->confirmPassword) ||
            !(preg_match($passwordRegex, $content->confirmPassword))
        ) {
            throw new Exception('Confirm password is missing or invalid!', 500);
        }
    }
}
