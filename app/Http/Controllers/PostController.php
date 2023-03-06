<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PostController extends Controller
{
    /**
     * Отображает список ресурсов
     *
     * @param Request $request
     * @return Application|Factory|View
     */
    public function index(Request $request)
    {
        $request->validate([
            'page' => 'integer'
        ]);

        $paginationOption = [0, 1, 30, 30];
        list($skip, $pageNumber, $perPage, $limit) = $paginationOption;
        if ($request->get('page') and $request->get('page') > 1) {
            $pageNumber = $request->get('page');
            $firstPagePostsCount = 30;
            $skip = $perPage * $pageNumber - $firstPagePostsCount;
        }

        $dummyJson = Http::get('https://dummyjson.com/posts', [
            'limit' => $limit,
            'skip' => $skip
        ])->object();

        $total = $dummyJson->total;
        $postsDummy = $dummyJson->posts;
        $posts = new LengthAwarePaginator($postsDummy, $total, $perPage, $pageNumber, [
                'path' => '/posts'
            ]
        );

        return view('posts.index', compact('posts'));
    }

    /**
     * Выводит форму для создания нового ресурса
     *
     * @return Application|Factory|View
     */
    public function create()
    {
        return view('posts.create');
    }

    /**
     * Помещает созданный ресурс в хранилище
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => ['required', 'string'],
            'body' => ['required', 'string'],
            'author_name' => ['required', 'string'],
        ]);

        $validateValuesPost = $request->all();

        $response = Http::post('https://dummyjson.com/posts/add', [
            'title' => $validateValuesPost['title'],
            'body' => $validateValuesPost['body'],
            'userId' => auth()->user()->getAuthIdentifier()
        ]);

        $postId = is_numeric($response->object()->id) ? (int)$response->object()->id : null;
        if (empty($postId)) {
            throw new NotFoundHttpException();
        }

        $query = Post::where(['id' => $postId])->get();
        $postId = !empty($query[0]) ? $query[0]->id : null;

        if (empty($postId)) {
            $post = new Post();
            $post->id = $postId;
            $post->author_name = $validateValuesPost['author_name'];
            $post->save();
        }

        return redirect()->route('posts.index')->with('success', 'Post created successfully.');
    }

    /**
     * Отображает указанный ресурс.
     *
     * @param Request $request
     * @return Application|Factory|View
     */
    public function show(Request $request)
    {
        $postIdByBequestUri = $request->getRequestUri();
        $post = Http::get('https://dummyjson.com' . $postIdByBequestUri)->object();

        if (empty($post->id)) {
            throw new NotFoundHttpException();
        }

        $query = Post::where(['id' => $post->id])->get();
        $postFromDb = $query[0] ?? null;
        $post->authorName = $postFromDb->author_name ?? null;

        return view('posts.show', compact('post'));
    }

    /**
     * Выводит форму для редактирования указанного ресурса
     *
     * @param Request $request
     * @return Application|Factory|View
     */
    public function edit(Request $request)
    {
        $postId = filter_var($request->segment(2), FILTER_VALIDATE_INT);
        $dummyJsonPost = Http::patch("https://dummyjson.com/posts/$postId")->object();

        if (empty($dummyJsonPost->id)) {
            throw new NotFoundHttpException();
        }

        $query = Post::where(['id' => $postId])->get();
        $postFromDb = $query[0] ?? null;
        if (isset($postFromDb)) {
            $dummyJsonPost->authorName = $postFromDb->getAttribute('author_name');
        }

        $post = $dummyJsonPost;

        return view('posts.edit', compact('post'));
    }

    /**
     * Обновляет указанный ресурс в хранилище
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function update(Request $request)
    {
        $request->validate([
            'title' => ['required', 'string'],
            'body' => ['required', 'string'],
            'author_name' => ['required', 'string'],
        ]);

        $postId = filter_var($request->segment(2), FILTER_VALIDATE_INT);
        $validateValues = $request->all();

        $dummyJsonPost = Http::patch('https://dummyjson.com/posts/' . $postId, [
            'title' => $validateValues['title'],
            'body' => $validateValues['body']
        ]);

        $postId = $dummyJsonPost->object()->id ?? null;

        if (empty($postId)) {
            throw new NotFoundHttpException();
        }

        $query = Post::where(['id'=> $postId])->get();
        $onePost = $query[0] ?? null;

        if (!empty($onePost)) {
            $onePost->author_name = $request['author_name'];
            $onePost->save();
        } else {
            $post = new Post();
            $post->id = $postId;
            $post->author_name = $request['author_name'];
            $post->save();
        }

        return redirect()->route('posts.index')->with('success', 'Post updated successfully');
    }

    /**
     * Удаляет указанный ресурс из хранилища
     *
     * @param Request $request
     * @param Post $post
     * @return RedirectResponse
     */
    public function destroy(Request $request, Post $post)
    {
        $postIdByBequestUri = $request->getRequestUri();
        $dummyJsonPost = Http::delete('https://dummyjson.com' . $postIdByBequestUri)->object();

        if (empty($dummyJsonPost->id)) {
            throw new NotFoundHttpException();
        }

        $post->delete();

        return redirect()->route('posts.index')
            ->with('success', 'post deleted successfully');
    }
}
