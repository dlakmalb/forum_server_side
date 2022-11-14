<?php

namespace App\Controller;

use App\Entity\Post;
use App\Entity\User;
use App\Utils\RepositoryTrait;
use DateTime;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

class PostController extends AbstractController
{
    use RepositoryTrait;

    const APPROVED = 'APPROVED';
    const PENDING  = 'PENDING';
    const DESC     = 'DESC';

    /**
     * Get all approved posts
     *
     * @return JsonResponse
     */
    public function getPosts(): JsonResponse
    {
        $posts = $this->getEntityRepository(Post::class)
            ->findBy(['status' => self::APPROVED], ['createdAt' => self::DESC]);

        $organizedPosts = $posts ? $this->organizePosts($posts) : [];

        return $this->json(
            ['result' => 'success', 'posts' => $organizedPosts],
            200,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Add a new post
     *
     * @return JsonResponse
     */
    public function addPost(): JsonResponse
    {
        $request = Request::createFromGlobals();
        $content = json_decode($request->getContent());
        $entityManager = $this->getEntityManager();

        // Validate request data
        $this->validateAddPostRequest($content);

        $isAdmin = $content->isAdmin;
        $user = $this->getEntityRepository(User::class)->find($content->userId);

        !$user && throw new Exception('User not found!', 500);

        $entityManager->getConnection()->beginTransaction();

        try {
            $post = new Post();

            $post->setTitle($content->title);
            $post->setContent($content->content);
            $post->setStatus($isAdmin ? self::APPROVED : self::PENDING);
            $post->setCreatedBy($user);
            $post->setCreatedAt(new DateTime());

            $entityManager->persist($post);
            $entityManager->flush();
            $entityManager->getConnection()->commit();
        } catch (Exception $error) {
            $entityManager->getConnection()->rollBack();
            throw $error;
        }

        return $this->json(
            ['result' => 'success'],
            201,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Get user posts to delete
     *
     * @return JsonResponse
     */
    public function getPostsForDelete(): JsonResponse
    {
        $request = Request::createFromGlobals();

        // Validate request data
        $this->validateGetPostsRequest($request->query);

        $isAdmin = $request->query->get('isAdmin') === 'true';
        $postRepository = $this->getEntityRepository(Post::class);

        $posts = $isAdmin ?
            $postRepository->findBy([], ['createdAt' => self::DESC]) :
            $postRepository->findBy(
                ['createdBy' => (int)$request->query->get('userId')],
                ['createdAt' => self::DESC]
            );

        $organizedPosts = $posts ? $this->organizePosts($posts) : [];

        return $this->json(
            ['result' => 'success', 'posts' => $organizedPosts],
            200,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Get user pending posts
     *
     * @return JsonResponse
     */
    public function getPendingPosts(): JsonResponse
    {
        $request = Request::createFromGlobals();

        // Validate request data
        $this->validateGetPostsRequest($request->query);

        $isAdmin = $request->query->get('isAdmin') === 'true';
        $postRepository = $this->getEntityRepository(Post::class);

        $posts = $isAdmin ?
            $postRepository->findBy(
                ['status' => self::PENDING],
                ['createdAt' => self::DESC]
            ) :
            $postRepository->findBy(
                [
                    'status' => self::PENDING,
                    'createdBy' => (int)$request->query->get('userId')
                ],
                ['createdAt' => self::DESC]
            );

        $organizedPosts = $posts ? $this->organizePosts($posts) : [];

        return $this->json(
            ['result' => 'success', 'posts' => $organizedPosts],
            200,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Delete a post
     *
     * @return JsonResponse
     */
    public function deletePost(): JsonResponse
    {
        $request = Request::createFromGlobals();
        $entityManager = $this->getEntityManager();

        // Validate request data
        if (
            !$request->query->has('postId') ||
            (int)$request->query->get('postId') <= 0
        ) {
            throw new Exception('Post id is missing or invalid!', 500);
        }

        $post = $this->getEntityRepository(Post::class)
            ->find((int)$request->query->get('postId'));

        !$post && throw new Exception('Post not found!', 500);

        $entityManager->getConnection()->beginTransaction();

        try {
            $comments = $post->getComments();

            foreach ($comments as $comment) $entityManager->remove($comment);

            $entityManager->remove($post);

            $entityManager->flush();
            $entityManager->getConnection()->commit();
        } catch (Exception $error) {
            $entityManager->getConnection()->rollBack();
            throw $error;
        }

        return $this->json(
            ['result' => 'success'],
            200,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Approve or reject a post
     *
     * @return JsonResponse
     */
    public function updatePost(): JsonResponse
    {
        $request = Request::createFromGlobals();
        $entityManager = $this->getEntityManager();

        // Validate request data
        $this->validateUpdatePost($request->query);

        $post = $this->getEntityRepository(Post::class)
            ->find((int)$request->query->get('postId'));

        !$post && throw new Exception('Post not found!', 500);

        $status = $request->query->get('status');

        $post->setStatus($status);
        $entityManager->flush();

        return $this->json(
            ['result' => 'success'],
            200,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Check the add post request is valid or not
     *
     * @param object $content
     * @return void
     */
    private function validateAddPostRequest(object $content): void
    {
        if (
            !property_exists($content, 'userId') ||
            (int)$content->userId <= 0
        ) {
            throw new Exception('User id is missing or invalid!', 500);
        }

        if (
            !property_exists($content, 'isAdmin') ||
            !is_bool($content->isAdmin)
        ) {
            throw new Exception('isAdmin property is missing or invalid!', 500);
        }

        if (
            !property_exists($content, 'title') ||
            !is_string($content->title) ||
            empty($content->title)
        ) {
            throw new Exception('Title is missing or invalid!', 500);
        }

        if (
            !property_exists($content, 'content') ||
            !is_string($content->content)
        ) {
            throw new Exception('Content is missing or invalid!', 500);
        }
    }

    /**
     * Check the get posts request is valid or not
     *
     * @param ParameterBag $parameterBag
     * @return void
     */
    private function validateGetPostsRequest(
        ParameterBag $parameterBag
    ): void {
        if (
            !$parameterBag->has('userId') ||
            (int)$parameterBag->get('userId') <= 0
        ) {
            throw new Exception('User id is missing or invalid!', 500);
        }

        if (
            !$parameterBag->has('isAdmin') ||
            !$parameterBag->get('isAdmin')
        ) {
            throw new Exception('isAdmin property is missing or invalid!', 500);
        }
    }

    /**
     * Check the update post request is valid or not
     *
     * @param ParameterBag $parameterBag
     * @return void
     */
    private function validateUpdatePost(
        ParameterBag $parameterBag
    ): void {
        if (
            !$parameterBag->has('postId') ||
            (int)$parameterBag->get('postId') <= 0
        ) {
            throw new Exception('Post id is missing or invalid!', 500);
        }

        if (
            !$parameterBag->has('status') ||
            !$parameterBag->get('status')
        ) {
            throw new Exception('Status is missing or invalid!', 500);
        }
    }

    /**
     * Organize posts
     *
     * @param array $posts
     * @return array
     */
    private function organizePosts(array $posts): array
    {
        $organizedPosts = [];

        foreach ($posts as $post) {
            $organizedPosts[] = [
                'id' => $post->getId(),
                'title' => $post->getTitle(),
                'content' => $post->getContent(),
                'status' => $post->getStatus(),
                'createdById' => $post->getCreatedBy()->getId(),
                'createdBy' => $post->getCreatedBy()->getUserName(),
                'createdAt' => $post->getCreatedAt()->format('l d F Y H:i')
            ];
        }

        return $organizedPosts;
    }
}
