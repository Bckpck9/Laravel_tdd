<?php

namespace App\Http\Controllers;

use App\Http\Requests\Post\StoreRequest;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Requests\UpdateRequest;



class PostController extends Controller
{
    use AuthorizesRequests;
    public function index(){
        $posts = Post::all();
        return response()->json($posts);

    }

    public function show(Post $post){
        return response()->json($post);
    }

    public function store(StoreRequest $request){
        $data = $request -> validated();
        $data['user_id'] = $request->user()->id;
        $data['image'] = $request->file('image')->store('posts', 'public');
        $post = Post::create($data);
        return response()->json($post,201);
    }

    public function update(UpdateRequest $request, Post $post)
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('posts', 'public');
        } else {
            unset($data['image']);
        }

        $post->update($data);

        return response()->json($post, 200);
    }

    public function delete(Post $post){
        $this->authorize('delete', $post);
        $post->delete();
        return response()->json([
            'message' => 'deleted'
        ]);
    }
}
