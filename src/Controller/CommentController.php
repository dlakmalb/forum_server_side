<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\User;
use App\Utils\RepositoryTrait;
use DateTime;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class CommentController extends AbstractController
{
    use RepositoryTrait;

    const DESC = 'DESC';

    /**
     * Get all comments
     *
     * @return JsonResponse
     */
    public function getComments(): JsonResponse
    {
        $request = Request::createFromGlobals();

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

        $organizedPosts = [
            'id' => $post->getId(),
            'title' => $post->getTitle(),
            'content' => $post->getContent(),
            'status' => $post->getStatus(),
            'createdById' => $post->getCreatedBy()->getId(),
            'createdBy' => $post->getCreatedBy()->getUserName(),
            'createdAt' => $post->getCreatedAt()->format('l d F Y H:i')
        ];

        $organizedComments = [];

        $comments = $this->getEntityRepository(Comment::class)
            ->findBy(
                ['post' => $post],
                ['createdAt' => self::DESC]
            );

        foreach ($comments as $comment) {
            $organizedComments[] = [
                'id' => $comment->getId(),
                'comment' => $comment->getComment(),
                'createdBy' => $comment->getCreatedBy()->getUserName(),
                'createdAt' => $comment->getCreatedAt()->format('l d F Y H:i')
            ];
        }

        return $this->json(
            [
                'result' => 'success',
                'post' => $organizedPosts,
                'comments' => $organizedComments
            ],
            200,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Add a comment to a post
     *
     * @return JsonResponse
     */
    public function addComment(): JsonResponse
    {
        $request = Request::createFromGlobals();
        $content = json_decode($request->getContent());
        $entityManager = $this->getEntityManager();

        // Validate request data
        $this->validateAddCommentRequest($content);

        $post = $this->getEntityRepository(Post::class)->find($content->postId);
        $user = $this->getEntityRepository(User::class)->find($content->userId);

        !$post && throw new Exception('Post not found!', 500);
        !$user && throw new Exception('User not found!', 500);

        $entityManager->getConnection()->beginTransaction();

        try {
            $newComment = new Comment();

            $newComment->setPost($post);
            $newComment->setComment($content->comment);
            $newComment->setCreatedBy($user);
            $newComment->setCreatedAt(new DateTime());

            $entityManager->persist($newComment);
            $entityManager->flush();

            $organizedComment = [
                'id' => $newComment->getId(),
                'comment' => $newComment->getComment(),
                'createdBy' => $newComment->getCreatedBy()->getUserName(),
                'createdAt' => $newComment->getCreatedAt()->format('l d F Y H:i')
            ];

            $entityManager->getConnection()->commit();
        } catch (Exception $error) {
            $entityManager->getConnection()->rollBack();
            throw $error;
        }

        return $this->json(
            ['result' => 'success', 'newComment' => $organizedComment],
            201,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Check the add comment request is valid or not
     *
     * @param object $content
     * @return void
     */
    private function validateAddCommentRequest(object $content): void
    {
        if (
            !property_exists($content, 'postId') ||
            (int)$content->postId <= 0
        ) {
            throw new Exception('Post id is missing or invalid!', 500);
        }

        if (
            !property_exists($content, 'userId') ||
            (int)$content->userId <= 0
        ) {
            throw new Exception('User id is missing or invalid!', 500);
        }

        if (
            !property_exists($content, 'comment') ||
            !is_string($content->comment) ||
            empty($content->comment)
        ) {
            throw new Exception('Comment is missing or invalid!', 500);
        }
    }
}
