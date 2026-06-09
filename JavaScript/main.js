function openModal(id, name) {
    document.getElementById('modalProductId').value = id;
    document.getElementById('modalProductName').innerText = "Add " + name + " to Cart";
    
    const modal = document.getElementById('unitModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex'); // Shows the modal
}

function closeModal() {
    const modal = document.getElementById('unitModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}



