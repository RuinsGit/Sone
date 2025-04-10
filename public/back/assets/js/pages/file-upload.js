'use strict';

let fileInputs = document.querySelectorAll('[type="file"]');
fileInputs.forEach(fileInput => {
    fileInput.addEventListener('change', function (e) {
        let files = e.target.files;
        if (!fileInput.multiple) fileInput.nextElementSibling.innerHTML = '';
        for (let file of files) {
            let reader = new FileReader;
            reader.addEventListener('loadend', function (e) {
                let col3 = document.createElement('div');
                col3.classList.add('col-md-3', 'col-sm-6', 'mb-3');
                let uploadImage = document.createElement('div');
                uploadImage.classList.add('upload-image');
                let img = document.createElement('img');
                img.setAttribute('src', e.target.result);
                col3.append(uploadImage);
                uploadImage.append(img);
                fileInput.nextElementSibling.append(col3);
            });
            reader.readAsDataURL(file);
        }
    })
});

let deleteImages = document.querySelectorAll('.upload-image i');

deleteImages.forEach(deleteImage => {
    deleteImage.addEventListener('click', function () {
        deleteImage.parentElement.parentElement.remove();
    });
});

