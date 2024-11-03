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
    <form>
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
          <label class="input-label" for="method_1">Method 1</label>
        </div>
        <div class="input-row">
          <input class="input" type="radio" id="method_2" name="method" value="method_2" required>
          <label class="input-label" for="method_2">Method 2</label>
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

    $(document).ready(function() {
      $('form').on('submit', function(e) {
        e.preventDefault();
        currentPage = 1;
        loadResults(currentPage);
      });

      $('#prevPage').on('click', function() {
        if (currentPage > 1) {
          currentPage--;
          loadResults(currentPage);
        }
      });

      $('#nextPage').on('click', function() {
        currentPage++;
        loadResults(currentPage);
      });

      function loadResults(page) {
        $("body").css("cursor", "wait");
        $('#submit ,#prevPage, #nextPage').prop('disabled', true);

        var formData = $('form').serialize() + '&page=' + page;
        $.ajax({
          url: 'result.php',
          type: 'POST',
          data: formData,
          dataType: 'json',
          success: function(response) {
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
            console.log(response.keyword)
            console.log(response.sources)
            console.log(response.method)
            response.data.forEach(item => {
              resultHtml += `<article class="result-article"><strong>Source:</strong> ${item.source}<br>
                             <strong>Original Text:</strong><br> ${item['original-text']}<br>
                             <strong>Preprocess Result:</strong><br> ${item['preprocess-result']}<br>
                             <strong>Similarity:</strong> ${item.similarity}</article>`;
            });

            $('#resultContent').html(resultHtml);
            $('#resultContent').show();

            $('#pageInfo').text(`Page ${response.current_page} of ${response.total_pages}`);

            $('#prevPage').toggle(response.current_page > 1);
            $('#nextPage').toggle(response.current_page < response.total_pages);


            $('html, body').animate({
              scrollTop: $('#results').offset().top
            }, 500);
          },
          error: function(xhr, status, error) {
            console.error("Submission failed: " + status + ", " + error);
            if (xhr.responseJSON && xhr.responseJSON.error) {
              alert("Error: " + xhr.responseJSON.error);
            } else {
              alert("An error occurred while submitting the form.");
            }
          },
          complete: function() {
            $('#submit, #prevPage, #nextPage').prop('disabled', false);
            $("body").css("cursor", "default");
          }
        });
      }
    });
  </script>
</body>

</html>