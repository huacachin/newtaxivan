
    <div class="app-wrapper d-block">
        <div class="">
            <!-- Body main section starts -->
            <main class="w-100 p-0">
                <!-- Login to your Account start -->
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-12 p-0">
                            <div class="login-form-container">
                                <div class="mb-4">
                                    <a class="logo d-inline-block" href="#">
                                        <img width="300px" style=" filter: brightness(0) saturate(100%) invert(24%) sepia(89%)
          saturate(346%) hue-rotate(135deg) brightness(90%) contrast(90%);" src="{{ asset('assets/images/logo/logo1.png') }}" alt="#" class="dark-logo">
                                    </a>
                                </div>
                                <div class="form_container">
                                    <form wire:submit.prevent="authenticate" class="app-form">
                                        @csrf
                                        <div class="mb-3 text-center">
                                            <h3>Iniciar sesión</h3>
                                        </div>
                                        <div class="mb-3">
                                            <label for="username" class="form-label">Usuario</label>
                                            <input  wire:model.defer="username" id="username"  type="text" class="form-control">
                                        </div>
                                        <div class="mb-3">
                                            <label for="password"  class="form-label">Contraseña</label>
                                            <input wire:model.defer="password" id="password" type="password" class="form-control">
                                        </div>
                                        <div class="mb-3 form-check">
                                            <input wire:model="remember" type="checkbox" class="form-check-input" >
                                            <label class="form-check-label" for="formCheck1">Recordar contraseña</label>
                                        </div>

                                        @error('username') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror

                                        <div>
                                            <button type="submit" role="button" class="btn btn-primary w-100">Mantener sesión</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Login to your Account end -->
            </main>
            <!-- Body main section ends -->
        </div>
    </div>

