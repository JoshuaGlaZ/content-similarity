<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="index.css">
  <title>Co-SIM</title>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
  <main>
    <div class="site__main">
      <h1 id="dynamic-shadow">
        What do you text?
      </h1>
    </div>
    <form id="searchForm">
      <div class="input-row">
        <div class="input-wrap">
          <label class="input-label" for="keyword">Keyword</label>
          <input class="input" type="text" name="keyword" placeholder="content to search..." required>
        </div>
      </div>
      <div class="divider"></div>
      <div class="input-group">
        <div class="input-row">
          <input class="input" type="checkbox" id="x" name="source[]" value="x">
          <label class="input-label" for="x">X</label>
        </div>

        <div class="input-row">
          <input class="input" type="checkbox" id="instagram" name="source[]" value="instagram">
          <label class="input-label" for="instagram">Instagram</label>
        </div>

        <div class="input-row">
          <input class="input" type="checkbox" id="youtube" name="source[]" value="youtube">
          <label class="input-label" for="youtube">Youtube</label>
        </div>
      </div>
      <div class="divider"></div>
      <div class="input-group">
        <div class="input-row">
          <input class="input" type="radio" id="method_1" name="method" value="method_1" required checked>
          <label class="input-label" for="method_1">Jaccard</label>
        </div>
        <div class="input-row">
          <input class="input" type="radio" id="method_2" name="method" value="method_2" required>
          <label class="input-label" for="method_2">Overlap</label>
        </div>
      </div>
      <div class="divider"></div>
      <div class="input-group">
        <div class="input-row">
          <input class="input" type="submit" id="submit" name="submit" value="Submit">
        </div>
      </div>
    </form>

    <!-- Output Section -->
    <div class="output" id="results">
      <div id="resultContent" style="display: none;"></div>
      <div id="pagination">
        <button id="prevPage" style="display: none;">Previous</button>
        <span id="pageInfo"></span>
        <button id="nextPage" style="display: none;">Next</button>
      </div>
    </div>
  </main>

  <script>
    let currentPage = 1;
    let totalPages = 1;
    let allResults = [];
    const contentPerPage = 5;

    $(document).ready(function() {
      $('#searchForm').on('submit', function(e) {
        e.preventDefault();
        currentPage = 1;
        loadResults();
      });

      $('#prevPage').on('click', function() {
        if (currentPage > 1) {
          currentPage--;
          displayResults(currentPage);
        }
      });

      $('#nextPage').on('click', function() {
        if (currentPage < totalPages) {
          currentPage++;
          displayResults(currentPage);
        }
      });

      function loadResults() {
        $("body").css("cursor", "wait");
        $('#submit, #prevPage, #nextPage').prop('disabled', true);

        const formData = $('#searchForm').serialize();
        $.ajax({
          url: 'result.php',
          type: 'POST',
          data: formData,
          dataType: 'json',
          success: function(response) {
            allResults = response.data;
            totalPages = Math.ceil(allResults.length / contentPerPage);
            displayResults(currentPage); // Display the first page
          },
          error: function(xhr, status, error) {
            console.error("Submission failed: " + status + ", " + error);
            alert("An error occurred while submitting the form.");
          },
          complete: function() {
            $('#submit, #prevPage, #nextPage').prop('disabled', false);
            $("body").css("cursor", "default");
          }
        });
      }

      function displayResults(page) {
        if (allResults.length === 0) {
          $('#resultContent').html("<p>No results found.</p>").show();
          $('#pagination').hide();
          return;
        }

        const start = (page - 1) * contentPerPage;
        const end = start + contentPerPage;
        const pageData = allResults.slice(start, end);
        console.log(pageData); 

        let resultHtml = `<div class='wave-result'>
            <span style="--i:1">~</span>
            <span style="--i:2">R</span> 
            <span style="--i:3">E</span> 
            <span style="--i:4">S</span> 
            <span style="--i:5">U</span> 
            <span style="--i:6">L</span> 
            <span style="--i:7">T</span> 
            <span style="--i:8">~</span>
            </div>`;

        pageData.forEach(item => {
          console.log(item); 
          resultHtml += `<article class="result-article">
                            <strong>Source:</strong> ${item.source} | <a href="${item.link}" target="_blank">${item.link}</a><br>                            <strong>Original Text:</strong><br> ${item['original-text']}<br>
                            <strong>Preprocess Result:</strong><br> ${item['preprocess-result']}<br>
                            <strong>Similarity:</strong> ${item['similarities']}
                         </article>`;
        });

        $('#resultContent').html(resultHtml).show();
        $('#pageInfo').text(`Page ${page} of ${totalPages}`);
        $('#prevPage').toggle(page > 1);
        $('#nextPage').toggle(page < totalPages);
        $('#pagination').show();

        $('html, body').animate({
          scrollTop: $('#results').offset().top
        }, 500);
      }
    });
  </script>
</body>

</html>