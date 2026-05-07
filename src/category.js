const allProduct = [
  { id: 1, name: Banana, price: 190, category: "Fruits", img: "banana.jpg" },
  { id: 2, name: Apple, price: 200, category: "Fruits", img: "apple.jpg" },
  {
    id: 3,
    name: Carrot,
    price: 100,
    category: "Vegetables",
    img: "carrot.jpg",
  },
  {
    id: 4,
    name: Broccoli,
    price: 150,
    category: "Vegetables",
    img: "broccoli.jpg",
  },
  { id: 5, name: Milk, price: 50, category: "Dairy", img: "milk.jpg" },
  { id: 6, name: Cheese, price: 300, category: "Dairy", img: "cheese.jpg" },
  { id: 7, name: Bread, price: 40, category: "Bakery", img: "bread.jpg" },
  { id: 8, name: Cake, price: 500, category: "Bakery", img: "cake.jpg" },
  { id: 9, name: Chicken, price: 250, category: "Meat", img: "chicken.jpg" },
  { id: 10, name: Beef, price: 400, category: "Meat", img: "beef.jpg" },
  { id: 11, name: Soda, price: 30, category: "Beverages", img: "soda.jpg" },
  { id: 12, name: Juice, price: 60, category: "Beverages", img: "juice.jpg" },
  { id: 13, name: Chips, price: 20, category: "Snacks", img: "chips.jpg" },
  { id: 14, name: Cookies, price: 80, category: "Snacks", img: "cookies.jpg" },
  {
    id: 15,
    name: Ice - Cream,
    price: 120,
    category: "Frozen",
    img: "icecream.jpg",
  },
  {
    id: 16,
    name: Frozen - Pizza,
    price: 150,
    category: "Frozen",
    img: "frozenpizza.jpg",
  },
];

// 1. Find out what the user clicked (e.g., ?type=vegetables)
const urlParams = new URLSearchParams(window.location.search);
const currentCategory = urlParams.get("type");

// 2. Target your HTML IDs
const titleTag = document.getElementById("category-name");
const productContainer = document.getElementById("product-list");

// 3. Update the Title
if (currentCategory) {
  titleTag.innerText =
    currentCategory.charAt(0).toUpperCase() + currentCategory.slice(1);
}

// 4. Filter the products
const filteredItems = allProducts.filter(
  (item) => item.category === currentCategory,
);

// 5. Build the HTML cards and "Inject" them
if (filteredItems.length > 0) {
  productContainer.innerHTML = ""; // Clear the "Loading..." or empty state

  filteredItems.forEach((product) => {
    productContainer.innerHTML += `
            <div class="border p-4 rounded-xl shadow-sm bg-white">
                <img src="${product.img}" class="w-full h-32 object-cover rounded-lg">
                <h3 class="font-bold mt-2">${product.name}</h3>
                <p class="text-green-600 font-bold">$${product.price}</p>
                <button class="bg-green-600 text-white w-full py-2 rounded-lg mt-2">
                    Add to Cart
                </button>
            </div>
        `;
  });
} else {
  productContainer.innerHTML = "<p>No products found in this category.</p>";
}
