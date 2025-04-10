<!-- ========== Left Sidebar Start ========== -->
<div class="vertical-menu" style="background-color: rgb(34, 33, 33);">

    <div data-simplebar class="h-100">

        <!-- User details -->
        <div class="user-profile text-center mt-3">
           
            <div class="mt-3">
                <h4 class="font-size-16 mb-1" style="color: white;">
                    {{ auth()->guard('admin')->user()->name ?? 'Administrator' }}
                </h4>
                <span class="text-muted"><i class="ri-record-circle-line align-middle font-size-14 text-success"></i>
                    Online</span>
            </div>
        </div>

        <!--- Sidemenu -->
        <div id="sidebar-menu" >
            <!-- Left Menu Start -->
            <ul class="metismenu list-unstyled" id="side-menu" style="color: white; " >
                <li class="menu-title" style="color: white; " >Menu</li>

                <li>
                    <a href="{{ route('admin.dashboard') }}" class="waves-effect">
                        <i class="ri-dashboard-line" style="color: white;"></i>
                        <span style="color: white;">Ana Səhifə</span>
                    </a>
                </li>

                <li>
                    <a href="javascript: void(0);" class="has-arrow waves-effect" style="background: #111; border-left: 3px solid #fff;">
                        <i class="ri-settings-3-line" style="color: white;"></i>
                        <span style="color: white;">Tənzimləmələr</span>
                    </a>
                    <ul class="sub-menu" style="background: #111; border-left: 3px solid #fff;">


                    <li>
                        <a href="{{ route('admin.ai.index') }}">
                            <i class="ri-brain-line"></i>
                            <span>AI Modelleri</span>
                        </a>
                    </li>

                      
                         

                        
   
                    </ul>
                </li>

              
                   
                    </ul>
                        
                </li>

              

            </ul>
        </div>
        <!-- Sidebar -->
    </div>
</div>
<!-- Left Sidebar End -->
