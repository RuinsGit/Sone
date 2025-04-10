@extends('166logistic.layouts.master')

@section('content')

    <section class="section">

        <div class="section-header">

            <h1>Profile</h1>

        </div>
        <div class="section-body">

            <div class="row mt-sm-4">

                <div class="col-12 col-md-12 col-lg-7">
                    <div class="card">
                        <form method="post" class="needs-validation" novalidate="" action="{{route('admin.profile.update')}}" enctype="multipart/form-data">
                            @csrf
                            <div class="card-header">
                                <h4>Update Profile</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="form-group col-12">
                                        <div class="mb-3">
                                            <img width="100px" src="{{asset(Auth::user()->image ? Auth::user()->image : 'uploads/default.jpg' )}}" alt="">
                                        </div>
                                        <label>Image</label>
                                        <input type="file" name="image" class="form-control">

                                    </div>

                                    <div class="form-group col-md-6 col-12">
                                        <label>Name</label>
                                        <input type="text" name="name" class="form-control" value="{{Auth::user()->name}}">

                                    </div>
                                    <div class="form-group col-md-6 col-12">
                                        <label>Email</label>
                                        <input type="text" name="email" class="form-control" value="{{Auth::user()->email}}" >

                                    </div>
                                </div>


                            </div>
                            <div class="card-footer d-flex justify-content-end">
                                <button class="btn btn-outline-success">Təsdiq et</button>
                            </div>

                        </form>
                    </div>
                </div>



            </div>
        </div>
    </section>
@endsection
